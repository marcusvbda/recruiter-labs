<?php

use App\Actions\CompleteConnectedIntegration;
use App\Actions\DisconnectConnectedIntegration;
use App\Contracts\OAuthIntegrationPlugin;
use App\Data\OAuthTokenData;
use App\Enums\ConnectedIntegrationStatus;
use App\Integrations\Gmail\GmailPlugin;
use App\Integrations\GoogleCalendar\GoogleCalendarPlugin;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Plan;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array', 'app.url' => 'http://localhost', 'services.google.redirect_uri' => null]);
    Plan::query()->create(['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'features' => [], 'limits' => []]);
});

function oauthTestPlugin(): OAuthTestPlugin
{
    $plugin = new OAuthTestPlugin;
    app()->instance(ConnectedIntegrationRegistry::class, new ConnectedIntegrationRegistry([$plugin]));

    return $plugin;
}

function oauthTestCompanyAndUser(): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($company);

    return [$company, $user];
}

test('a tenant member can connect Google Calendar with one-time state and encrypted tokens', function (): void {
    $plugin = oauthTestPlugin();
    [$company, $user] = oauthTestCompanyAndUser();
    $returnUrl = "http://localhost/admin/{$company->slug}/integrations/calendar-settings";

    $response = $this->actingAs($user)->withHeader('Referer', $returnUrl)->get(route('integrations.oauth.connect', [
        'company' => $company,
        'plugin' => 'google-calendar',
    ]));

    $response->assertRedirectContains('https://oauth.example/authorize');
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    $callback = $this->actingAs($user)->get(route('integrations.oauth.callback', [
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]));

    $callback->assertRedirect($returnUrl)
        ->assertSessionHas('connected_integration_status', 'connected');

    $integration = ConnectedIntegration::query()->sole();
    expect($integration->company_id)->toBe($company->id)
        ->and($integration->user_id)->toBe($user->id)
        ->and($integration->status)->toBe(ConnectedIntegrationStatus::Connected)
        ->and($integration->access_token)->toBe('access-token')
        ->and($integration->refresh_token)->toBe('refresh-token')
        ->and($integration->account_email)->toBe('person@example.com')
        ->and($integration->account_name)->toBe('Calendar Person')
        ->and(DB::table('connected_integrations')->value('access_token'))->not->toBe('access-token')
        ->and(DB::table('connected_integrations')->value('refresh_token'))->not->toBe('refresh-token')
        ->and($plugin->validatedTokens)->toBe(['access-token']);

    $this->actingAs($user)->get(route('integrations.oauth.callback', [
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]))->assertSessionHas('connected_integration_status', 'error');

    expect($plugin->exchangeCount)->toBe(1);
});

test('another tenant user cannot begin or complete the connection', function (): void {
    $plugin = oauthTestPlugin();
    [$company, $owner] = oauthTestCompanyAndUser();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('integrations.oauth.connect', [
        'company' => $company,
        'plugin' => 'google-calendar',
    ]))->assertForbidden();

    $response = $this->actingAs($owner)->get(route('integrations.oauth.connect', [
        'company' => $company,
        'plugin' => 'google-calendar',
    ]));
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($outsider)->get(route('integrations.oauth.callback', [
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]))->assertSessionHas('connected_integration_status', 'error');

    expect(ConnectedIntegration::query()->exists())->toBeFalse()
        ->and($plugin->exchangeCount)->toBe(0);

    $this->actingAs($owner)->get(route('integrations.oauth.callback', [
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]))->assertSessionHas('connected_integration_status', 'connected');

    expect($plugin->exchangeCount)->toBe(1);
});

test('an unauthenticated connection request redirects to the Filament login', function (): void {
    oauthTestPlugin();
    $company = Company::factory()->create();

    $this->get(route('integrations.oauth.connect', [
        'company' => $company,
        'plugin' => 'google-calendar',
    ]))->assertRedirect(route('filament.admin.auth.login'));
});

test('disconnect clears only the selected local integration credentials', function (): void {
    $plugin = oauthTestPlugin();
    [$company, $user] = oauthTestCompanyAndUser();
    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'refresh_token' => 'refresh-token',
    ]);

    $this->actingAs($user)->delete(route('integrations.oauth.disconnect', [
        'company' => $company,
        'plugin' => 'google-calendar',
    ]))->assertSessionHas('connected_integration_status', 'disconnected');

    $integration->refresh();
    expect($plugin->disconnectedIntegrationIds)->toBe([$integration->getKey()])
        ->and($integration->status)->toBe(ConnectedIntegrationStatus::Revoked)
        ->and($integration->access_token)->toBeNull()
        ->and($integration->refresh_token)->toBeNull();
});

test('another recruiter cannot disconnect an integration they do not own', function (): void {
    oauthTestPlugin();
    [$company, $owner] = oauthTestCompanyAndUser();
    $otherUser = User::factory()->create();
    $otherUser->companies()->attach($company);
    ConnectedIntegration::factory()->create(['company_id' => $company, 'user_id' => $owner]);

    $this->actingAs($otherUser)->delete(route('integrations.oauth.disconnect', [
        'company' => $company,
        'plugin' => 'google-calendar',
    ]))->assertNotFound();
});

test('disconnecting Calendar does not revoke or clear a sibling Gmail integration', function (): void {
    [$company, $user] = oauthTestCompanyAndUser();
    $calendar = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'plugin_key' => 'google-calendar',
        'access_token' => 'calendar-access',
        'refresh_token' => 'calendar-refresh',
    ]);
    $gmail = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'plugin_key' => 'gmail',
        'access_token' => 'gmail-access',
        'refresh_token' => 'gmail-refresh',
    ]);
    Http::preventStrayRequests();
    $http = app(HttpFactory::class);
    $action = new DisconnectConnectedIntegration(new ConnectedIntegrationRegistry([
        new GoogleCalendarPlugin($http),
        new GmailPlugin($http),
    ]));

    $action->run($company, $user, 'google-calendar');

    $calendar->refresh();
    $gmail->refresh();
    expect($calendar->status)->toBe(ConnectedIntegrationStatus::Revoked)
        ->and($calendar->access_token)->toBeNull()
        ->and($gmail->status)->toBe(ConnectedIntegrationStatus::Connected)
        ->and($gmail->access_token)->toBe('gmail-access')
        ->and($gmail->refresh_token)->toBe('gmail-refresh');
    Http::assertNothingSent();
});

test('Google product plugins request isolated scopes', function (): void {
    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
        'services.google.redirect_uri' => 'http://localhost/integrations/oauth/callback',
    ]);
    $http = app(HttpFactory::class);
    $calendarUrl = (new GoogleCalendarPlugin($http))->authorizationUrl('state', str_repeat('a', 96), 'http://localhost/integrations/oauth/callback');
    $gmailUrl = (new GmailPlugin($http))->authorizationUrl('state', str_repeat('b', 96), 'http://localhost/integrations/oauth/callback');
    parse_str((string) parse_url($calendarUrl, PHP_URL_QUERY), $calendarQuery);
    parse_str((string) parse_url($gmailUrl, PHP_URL_QUERY), $gmailQuery);

    expect($calendarQuery['scope'])->toContain('calendar.events')
        ->not->toContain('gmail.send')
        ->and($gmailQuery['scope'])->toContain('gmail.send')
        ->not->toContain('calendar.events')
        ->and($calendarQuery)->not->toHaveKey('include_granted_scopes')
        ->and($gmailQuery)->not->toHaveKey('include_granted_scopes');
});

test('reconnecting a different Google account cannot reuse the previous accounts refresh token', function (): void {
    [$company, $user] = oauthTestCompanyAndUser();
    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company,
        'user_id' => $user,
        'external_account_id' => 'account-a',
        'access_token' => 'old-access',
        'refresh_token' => 'account-a-refresh',
    ]);
    $action = new CompleteConnectedIntegration;

    expect(fn () => $action->run($company, $user, 'google-calendar', new OAuthTokenData(
        accessToken: 'account-b-access',
        refreshToken: null,
        expiresAt: now()->addHour()->timestamp,
        externalAccountId: 'account-b',
    )))->toThrow(RuntimeException::class);

    $integration->refresh();
    expect($integration->external_account_id)->toBe('account-a')
        ->and($integration->access_token)->toBe('old-access')
        ->and($integration->refresh_token)->toBe('account-a-refresh');
});

class OAuthTestPlugin implements OAuthIntegrationPlugin
{
    public int $exchangeCount = 0;

    public array $validatedTokens = [];

    public array $disconnectedIntegrationIds = [];

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
        return route('integrations.oauth.callback');
    }

    public function authorizationUrl(string $state, string $codeVerifier, string $redirectUri): string
    {
        return 'https://oauth.example/authorize?'.http_build_query(['state' => $state]);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): OAuthTokenData
    {
        $this->exchangeCount++;

        return new OAuthTokenData('access-token', 'refresh-token', now()->addHour()->timestamp, ['calendar.events'], 'google-sub', 'person@example.com', 'Calendar Person', ['locale' => 'en']);
    }

    public function refreshAccessToken(string $refreshToken, string $redirectUri): OAuthTokenData
    {
        throw new LogicException('Not used.');
    }

    public function validateConnection(OAuthTokenData $token): void
    {
        $this->validatedTokens[] = $token->accessToken;
    }

    public function afterConnected(ConnectedIntegration $integration): void {}

    public function disconnect(ConnectedIntegration $integration): void
    {
        $this->disconnectedIntegrationIds[] = $integration->getKey();
    }
}
