<?php

use App\Contracts\RecruitmentEmailSender;
use App\Data\StatusEmailContext;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailNotificationType;
use App\Enums\EmailProvider;
use App\Jobs\SendRecruitmentEmail;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\Plan;
use App\Services\NativeRecruitmentMailFactory;
use App\Services\RecruitmentEmailSenderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the registry routes each provider to its own sender', function (): void {
    $resend = Mockery::mock(RecruitmentEmailSender::class);
    $resend->shouldReceive('provider')->once()->andReturn(EmailProvider::Resend);
    $gmail = Mockery::mock(RecruitmentEmailSender::class);
    $gmail->shouldReceive('provider')->once()->andReturn(EmailProvider::Gmail);

    $registry = new RecruitmentEmailSenderRegistry([$resend, $gmail]);

    expect($registry->sender(EmailProvider::Resend))->toBe($resend)
        ->and($registry->sender(EmailProvider::Gmail))->toBe($gmail);
});

test('the queued job sends only through the selected tenant provider', function (EmailProvider $selected): void {
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
    $company = Company::factory()->create();
    $setting = CompanyEmailProviderSetting::query()->create([
        'company_id' => $company->getKey(),
        'provider' => $selected,
        'api_key' => $selected === EmailProvider::Resend ? 'resend-key' : null,
        'from_address' => 'recruiting@example.com',
        'credential_status' => EmailCredentialStatus::Active,
        'is_default' => true,
    ]);
    $resend = Mockery::mock(RecruitmentEmailSender::class);
    $resend->shouldReceive('provider')->once()->andReturn(EmailProvider::Resend);
    $gmail = Mockery::mock(RecruitmentEmailSender::class);
    $gmail->shouldReceive('provider')->once()->andReturn(EmailProvider::Gmail);
    $selectedSender = $selected === EmailProvider::Resend ? $resend : $gmail;
    $otherSender = $selected === EmailProvider::Resend ? $gmail : $resend;
    $selectedSender->shouldReceive('isReady')->once()->with(Mockery::type(CompanyEmailProviderSetting::class))->andReturnTrue();
    $selectedSender->shouldReceive('send')->once();
    $otherSender->shouldNotReceive('isReady');
    $otherSender->shouldNotReceive('send');
    Cache::flush();
    $job = new SendRecruitmentEmail(
        $company->id,
        $setting->id,
        EmailNotificationType::PipelineStatus,
        new StatusEmailContext(99, 7, 'candidate@example.com', $company->name, 'Your application', '<p>Hi Taylor</p>', 1700000000),
    );

    $job->handle(new NativeRecruitmentMailFactory, new RecruitmentEmailSenderRegistry([$resend, $gmail]));
})->with([
    'Resend' => EmailProvider::Resend,
    'Gmail' => EmailProvider::Gmail,
]);
