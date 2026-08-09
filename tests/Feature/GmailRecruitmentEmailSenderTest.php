<?php

use App\Actions\SetDefaultCompanyEmailProvider;
use App\Contracts\RecruitmentEmailSender;
use App\Data\ApplicationEmailContext;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\RecruitmentEmailDeliveryStatus;
use App\Mail\Recruitment\ApplicationReceivedMail;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\ConnectedIntegration;
use App\Models\Plan;
use App\Models\RecruitmentEmailDelivery;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use App\Services\ConnectedIntegrationTokenManager;
use App\Services\GmailRecruitmentEmailSender;
use App\Services\RecruitmentEmailSenderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('Gmail delivery posts a base64url RFC 5322 message without a real Google call', function (): void {
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
    $company = Company::factory()->create(['name' => 'Acme Recruiting']);
    $user = User::factory()->create();
    $user->companies()->attach($company);
    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'plugin_key' => 'gmail',
        'status' => ConnectedIntegrationStatus::Connected,
        'account_email' => 'recruiting@example.com',
    ]);
    $setting = CompanyEmailProviderSetting::query()->create([
        'company_id' => $company->getKey(),
        'connected_integration_id' => $integration->getKey(),
        'provider' => EmailProvider::Gmail,
        'from_address' => 'recruiting@example.com',
        'credential_status' => EmailCredentialStatus::Active,
        'validated_at' => now(),
        'is_default' => true,
    ]);
    $tokens = Mockery::mock(ConnectedIntegrationTokenManager::class);
    $tokens->shouldReceive('accessToken')->once()->andReturn('gmail-access-token');
    Http::preventStrayRequests();
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'message-id'])]);
    $sender = new GmailRecruitmentEmailSender(app(Markdown::class), app(Factory::class), $tokens);
    $mail = new ApplicationReceivedMail(new ApplicationEmailContext(42, 'Taylor', 'candidate@example.com', 'Engineer', $company->name));

    $sender->send($setting, $mail, 'candidate@example.com', $company->name, 'stable-key');

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            || $request->hasHeader('Authorization', 'Bearer gmail-access-token') === false) {
            return false;
        }

        $raw = (string) $request['raw'];
        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        return is_string($decoded)
            && str_contains($decoded, 'To: candidate@example.com')
            && str_contains($decoded, 'X-RecruiterLabs-Idempotency-Key: stable-key');
    });
});

test('Gmail authentication failures require reauthorization while server failures preserve credentials', function (int $status, array $body, bool $requiresReauthorization): void {
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
    $company = Company::factory()->create(['name' => 'Acme Recruiting']);
    $user = User::factory()->create();
    $user->companies()->attach($company);
    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'plugin_key' => 'gmail',
        'status' => ConnectedIntegrationStatus::Connected,
        'account_email' => 'recruiting@example.com',
        'expires_at' => now()->addHour(),
    ]);
    $setting = CompanyEmailProviderSetting::query()->create([
        'company_id' => $company->getKey(),
        'connected_integration_id' => $integration->getKey(),
        'provider' => EmailProvider::Gmail,
        'from_address' => 'recruiting@example.com',
        'credential_status' => EmailCredentialStatus::Active,
        'validated_at' => now(),
        'is_default' => true,
    ]);
    Http::preventStrayRequests();
    Http::fake(['gmail.googleapis.com/*' => Http::response($body, $status)]);
    $tokens = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([]));
    $sender = new GmailRecruitmentEmailSender(app(Markdown::class), app(Factory::class), $tokens);
    $mail = new ApplicationReceivedMail(new ApplicationEmailContext(42, 'Taylor', 'candidate@example.com', 'Engineer', $company->name));

    try {
        $sender->send($setting, $mail, 'candidate@example.com', $company->name, 'stable-key');
    } catch (Throwable) {
    }

    $integration->refresh();

    if ($requiresReauthorization) {
        expect($integration->status)->toBe(ConnectedIntegrationStatus::ReauthorizationRequired)
            ->and($integration->access_token)->toBeNull()
            ->and($integration->refresh_token)->toBeNull();
    } else {
        expect($integration->status)->toBe(ConnectedIntegrationStatus::Connected)
            ->and($integration->access_token)->not->toBeNull()
            ->and($integration->refresh_token)->not->toBeNull();

        try {
            $sender->send($setting, $mail, 'candidate@example.com', $company->name, 'stable-key');
        } catch (Throwable) {
        }

        expect(RecruitmentEmailDelivery::query()->sole()->status)->toBe(RecruitmentEmailDeliveryStatus::Pending)
            ->and(RecruitmentEmailDelivery::query()->sole()->attempts)->toBe(2);
        Http::assertSentCount(2);
    }
})->with([
    'expired access token' => [401, ['error' => ['message' => 'Invalid credentials']], true],
    'missing granted permission' => [403, ['error' => ['errors' => [['reason' => 'insufficientPermissions']]]], true],
    'temporary server error' => [503, ['error' => ['message' => 'Unavailable']], false],
]);

test('setting Gmail as default relinks it to the acting recruiters own connection', function (): void {
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
    $company = Company::factory()->create();
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $company->users()->attach([$firstUser->getKey(), $secondUser->getKey()]);
    $firstIntegration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $firstUser,
        'plugin_key' => 'gmail',
        'account_email' => 'first@example.com',
    ]);
    $secondIntegration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $secondUser,
        'plugin_key' => 'gmail',
        'account_email' => 'second@example.com',
    ]);
    CompanyEmailProviderSetting::query()->create([
        'company_id' => $company->getKey(),
        'connected_integration_id' => $firstIntegration->getKey(),
        'provider' => EmailProvider::Gmail,
        'from_address' => 'first@example.com',
        'credential_status' => EmailCredentialStatus::Active,
    ]);
    $gmailSender = Mockery::mock(RecruitmentEmailSender::class);
    $gmailSender->shouldReceive('provider')->once()->andReturn(EmailProvider::Gmail);
    $gmailSender->shouldReceive('isReady')->once()->andReturnTrue();
    $action = new SetDefaultCompanyEmailProvider(new RecruitmentEmailSenderRegistry([$gmailSender]));

    $setting = $action->run($company, $secondUser, EmailProvider::Gmail);

    expect($setting->connected_integration_id)->toBe($secondIntegration->id)
        ->and($setting->from_address)->toBe('second@example.com')
        ->and($setting->is_default)->toBeTrue();
});

test('an ambiguous Gmail connection failure is recorded once and is never automatically resent', function (): void {
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
    $company = Company::factory()->create(['name' => 'Acme Recruiting']);
    $user = User::factory()->create();
    $user->companies()->attach($company);
    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'plugin_key' => 'gmail',
        'status' => ConnectedIntegrationStatus::Connected,
        'account_email' => 'recruiting@example.com',
        'expires_at' => now()->addHour(),
    ]);
    $setting = CompanyEmailProviderSetting::query()->create([
        'company_id' => $company->getKey(),
        'connected_integration_id' => $integration->getKey(),
        'provider' => EmailProvider::Gmail,
        'from_address' => 'recruiting@example.com',
        'credential_status' => EmailCredentialStatus::Active,
        'is_default' => true,
    ]);
    Http::preventStrayRequests();
    Http::fake(['gmail.googleapis.com/*' => Http::failedConnection('timed out')]);
    $sender = new GmailRecruitmentEmailSender(
        app(Markdown::class),
        app(Factory::class),
        new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([])),
    );
    $mail = new ApplicationReceivedMail(new ApplicationEmailContext(42, 'Taylor', 'candidate@example.com', 'Engineer', $company->name));

    $sender->send($setting, $mail, 'candidate@example.com', $company->name, 'stable-ambiguous-key');
    $sender->send($setting, $mail, 'candidate@example.com', $company->name, 'stable-ambiguous-key');

    Http::assertSentCount(1);
    $delivery = RecruitmentEmailDelivery::query()->sole();
    expect($delivery->status)->toBe(RecruitmentEmailDeliveryStatus::Ambiguous)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_exception_class)->toBe(ConnectionException::class);
});
