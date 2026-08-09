<?php

use App\Contracts\OAuthIntegrationPlugin;
use App\Data\OAuthTokenData;
use App\Enums\ConnectedIntegrationStatus;
use App\Exceptions\ConnectedIntegrationReauthorizationRequired;
use App\Exceptions\OAuthRefreshTokenRejected;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Plan;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use App\Services\ConnectedIntegrationTokenManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array', 'services.google.redirect_uri' => 'http://localhost/integrations/oauth/callback']);
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
});

test('expired credentials are refreshed and rotated', function (): void {
    $plugin = new TokenManagerTestPlugin;
    $manager = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([$plugin]));
    [$integration, $company, $user] = tokenManagerIntegration();

    expect($manager->accessToken($company, $user, 'google-calendar'))->toBe('new-access-token');

    $integration->refresh();
    expect($integration->access_token)->toBe('new-access-token')
        ->and($integration->refresh_token)->toBe('new-refresh-token')
        ->and($integration->status)->toBe(ConnectedIntegrationStatus::Connected)
        ->and($integration->last_refreshed_at)->not->toBeNull();
});

test('a refresh failure clears credentials and requires reauthorization', function (): void {
    $plugin = new TokenManagerTestPlugin;
    $plugin->failRefresh = true;
    $manager = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([$plugin]));
    [$integration, $company, $user] = tokenManagerIntegration();

    expect(fn () => $manager->accessToken($company, $user, 'google-calendar'))
        ->toThrow(ConnectedIntegrationReauthorizationRequired::class);

    $integration->refresh();
    expect($integration->status)->toBe(ConnectedIntegrationStatus::ReauthorizationRequired)
        ->and($integration->access_token)->toBeNull()
        ->and($integration->refresh_token)->toBeNull()
        ->and($integration->last_error_at)->not->toBeNull();
});

test('a transient refresh failure preserves credentials and connected status', function (): void {
    $plugin = new TokenManagerTestPlugin;
    $plugin->failTransiently = true;
    $manager = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([$plugin]));
    [$integration, $company, $user] = tokenManagerIntegration();

    expect(fn () => $manager->accessToken($company, $user, 'google-calendar'))
        ->toThrow(RuntimeException::class, 'temporary outage');

    $integration->refresh();
    expect($integration->status)->toBe(ConnectedIntegrationStatus::Connected)
        ->and($integration->access_token)->toBe('expired-access-token')
        ->and($integration->refresh_token)->toBe('old-refresh-token')
        ->and($integration->last_error_at)->not->toBeNull();
});

test('a recruiter cannot resolve another recruiters token', function (): void {
    $manager = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([new TokenManagerTestPlugin]));
    [, $company] = tokenManagerIntegration();
    $otherUser = User::factory()->create();
    $otherUser->companies()->attach($company);

    expect(fn () => $manager->accessToken($company, $otherUser, 'google-calendar'))
        ->toThrow(ModelNotFoundException::class);
});

test('a multi-tenant recruiter cannot resolve a connection through the wrong tenant', function (): void {
    $manager = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([new TokenManagerTestPlugin]));
    [, $firstCompany, $user] = tokenManagerIntegration();
    $secondCompany = Company::factory()->create();
    $user->companies()->attach($secondCompany);

    expect(fn () => $manager->accessToken($secondCompany, $user, 'google-calendar'))
        ->toThrow(ModelNotFoundException::class);

    expect($firstCompany->is($secondCompany))->toBeFalse();
});

function tokenManagerIntegration(): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($company);

    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'access_token' => 'expired-access-token',
        'refresh_token' => 'old-refresh-token',
        'expires_at' => now()->subMinute(),
    ]);

    return [$integration, $company, $user];
}

class TokenManagerTestPlugin implements OAuthIntegrationPlugin
{
    public bool $failRefresh = false;

    public bool $failTransiently = false;

    public function key(): string
    {
        return 'google-calendar';
    }

    public function label(): string
    {
        return 'Google Calendar';
    }

    public function description(): string
    {
        return 'Test';
    }

    public function category(): string
    {
        return 'Calendars';
    }

    public function icon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    public function capabilities(): array
    {
        return ['calendar.events'];
    }

    public function redirectUri(): string
    {
        return 'http://localhost/integrations/oauth/google-calendar/callback';
    }

    public function authorizationUrl(string $state, string $codeVerifier, string $redirectUri): string
    {
        throw new LogicException('Not used.');
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): OAuthTokenData
    {
        throw new LogicException('Not used.');
    }

    public function refreshAccessToken(string $refreshToken, string $redirectUri): OAuthTokenData
    {
        if ($this->failRefresh) {
            throw new OAuthRefreshTokenRejected('invalid_grant');
        }
        if ($this->failTransiently) {
            throw new RuntimeException('temporary outage');
        }

        return new OAuthTokenData('new-access-token', 'new-refresh-token', now()->addHour()->timestamp, ['calendar.events']);
    }

    public function validateConnection(OAuthTokenData $token): void {}

    public function afterConnected(ConnectedIntegration $integration): void {}

    public function disconnect(ConnectedIntegration $integration): void {}
}
