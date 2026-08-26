<?php

use App\Actions\SetWorkspaceMemberAccess;
use App\Contracts\OAuthIntegrationPlugin;
use App\Data\OAuthTokenData;
use App\Enums\CompanyRole;
use App\Enums\ConnectedIntegrationStatus;
use App\Exceptions\WorkspaceOwnerAccessCannotBeChanged;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\ConnectedIntegration;
use App\Models\Plan;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Services\ConnectedIntegrationRegistry;
use App\Services\ConnectedIntegrationTokenManager;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

function accessWorkspaceWithOwnerAndMember(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company->users()->attach($owner, ['role' => CompanyRole::Owner->value]);
    $company->users()->attach($member, ['role' => CompanyRole::Member->value]);

    return [$company, $owner, $member];
}

function setAccessAction(): SetWorkspaceMemberAccess
{
    return app(SetWorkspaceMemberAccess::class);
}

test('a Member cannot change access directly, including their own and across workspaces', function (): void {
    [$company, , $member] = accessWorkspaceWithOwnerAndMember();
    $otherMember = User::factory()->create();
    $company->users()->attach($otherMember, ['role' => CompanyRole::Member->value]);

    expect(fn () => setAccessAction()->handle($company, $member, $otherMember, false))
        ->toThrow(AuthorizationException::class);

    expect(fn () => setAccessAction()->handle($company, $member, $member, false))
        ->toThrow(AuthorizationException::class);

    [$otherCompany, , $otherCompanyMember] = accessWorkspaceWithOwnerAndMember();

    // $member is a Member, not an Owner, anywhere — including in $otherCompany,
    // where they have no membership at all — so acting there is refused too.
    expect(fn () => setAccessAction()->handle($otherCompany, $otherCompanyMember, $member, false))
        ->toThrow(AuthorizationException::class);
});

test('the Owner access can never be disabled, by themselves or anyone else', function (): void {
    [$company, $owner] = accessWorkspaceWithOwnerAndMember();

    expect(fn () => setAccessAction()->handle($company, $owner, $owner, false))
        ->toThrow(WorkspaceOwnerAccessCannotBeChanged::class);

    expect($company->hasWorkspaceAccess($owner))->toBeTrue();
});

test('a Member with disabled access fails canAccessTenant, getTenants, update and viewTeam', function (): void {
    [$company, $owner, $member] = accessWorkspaceWithOwnerAndMember();

    setAccessAction()->handle($company, $member, $owner, false);

    expect($member->canAccessTenant($company))->toBeFalse()
        ->and($member->getTenants(Filament::getDefaultPanel())->pluck('id'))->not->toContain($company->id);

    $policy = new CompanyPolicy;
    expect($policy->update($member, $company))->toBeFalse()
        ->and($policy->viewTeam($member, $company))->toBeFalse();
});

test('disabling access keeps the membership row, role and Team listing, and does not touch the account or other workspaces', function (): void {
    [$company, $owner, $member] = accessWorkspaceWithOwnerAndMember();
    $otherCompany = Company::factory()->create();
    $otherCompany->users()->attach($member, ['role' => CompanyRole::Member->value]);

    setAccessAction()->handle($company, $member, $owner, false);

    expect($company->roleFor($member))->toBe(CompanyRole::Member)
        ->and($company->activeMembers()->get()->pluck('id'))->toContain($member->id)
        ->and($member->fresh())->not->toBeNull()
        ->and($otherCompany->hasWorkspaceAccess($member))->toBeTrue();
});

test('re-enabling access restores authorization immediately without creating a second membership or a new invitation', function (): void {
    [$company, $owner, $member] = accessWorkspaceWithOwnerAndMember();

    setAccessAction()->handle($company, $member, $owner, false);
    expect($company->hasWorkspaceAccess($member))->toBeFalse();

    setAccessAction()->handle($company, $member, $owner, true);

    expect($company->hasWorkspaceAccess($member))->toBeTrue()
        ->and($company->users()->wherePivot('user_id', $member->id)->count())->toBe(1);
});

test('SetWorkspaceMemberAccess is idempotent in both directions', function (): void {
    [$company, $owner, $member] = accessWorkspaceWithOwnerAndMember();

    setAccessAction()->handle($company, $member, $owner, true);
    setAccessAction()->handle($company, $member, $owner, true);
    expect($company->hasWorkspaceAccess($member))->toBeTrue();

    setAccessAction()->handle($company, $member, $owner, false);
    setAccessAction()->handle($company, $member, $owner, false);
    expect($company->hasWorkspaceAccess($member))->toBeFalse();
});

test('integration access token is refused once workspace access is disabled but the integration row survives', function (): void {
    [$company, $owner, $member] = accessWorkspaceWithOwnerAndMember();

    $plugin = new WorkspaceAccessTestPlugin;
    $manager = new ConnectedIntegrationTokenManager(new ConnectedIntegrationRegistry([$plugin]));

    $integration = ConnectedIntegration::factory()->create([
        'company_id' => $company->id,
        'user_id' => $member->id,
        'plugin_key' => 'workspace-access-test',
        'status' => ConnectedIntegrationStatus::Connected,
        'access_token' => 'valid-access-token',
        'refresh_token' => 'valid-refresh-token',
        'expires_at' => now()->addHour(),
    ]);

    expect($manager->accessToken($company, $member, 'workspace-access-test'))->toBe('valid-access-token');

    setAccessAction()->handle($company, $member, $owner, false);

    expect(fn () => $manager->accessToken($company, $member, 'workspace-access-test'))
        ->toThrow(AuthorizationException::class);

    expect(ConnectedIntegration::query()->whereKey($integration->id)->exists())->toBeTrue();
});

test('the invitation page tells a disabled member their access is off instead of offering the workspace', function (): void {
    [$company, $owner, $member] = accessWorkspaceWithOwnerAndMember();
    $member->forceFill(['email_verified_at' => now()])->save();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->getKey(),
        'email' => $member->email,
        'invited_by_id' => $owner->getKey(),
    ]);
    $token = CompanyInvitation::generateToken();
    $invitation->forceFill(['token_hash' => CompanyInvitation::hashToken($token)])->save();

    // While their access is on, the invitation has nothing left to do and points
    // them at the workspace they already belong to.
    $this->actingAs($member)
        ->get(route('workspace-invitations.show', ['token' => $token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', 'already_member')
            ->where('urls.workspace', fn (?string $url): bool => filled($url)));

    setAccessAction()->handle($company, $member, $owner, false);

    // With access off they are still on the team, so they must not be told they
    // have access, and must not be handed a link the workspace would refuse.
    $this->actingAs($member)
        ->get(route('workspace-invitations.show', ['token' => $token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', 'access_disabled')
            ->where('urls.workspace', null)
            ->where('urls.accept', null));
});

class WorkspaceAccessTestPlugin implements OAuthIntegrationPlugin
{
    public function key(): string
    {
        return 'workspace-access-test';
    }

    public function label(): string
    {
        return 'Test Plugin';
    }

    public function description(): string
    {
        return 'Test';
    }

    public function category(): string
    {
        return 'Test';
    }

    public function icon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function redirectUri(): string
    {
        return 'http://localhost/integrations/oauth/workspace-access-test/callback';
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
        throw new LogicException('Not used.');
    }

    public function validateConnection(OAuthTokenData $token): void {}

    public function afterConnected(ConnectedIntegration $integration): void {}

    public function disconnect(ConnectedIntegration $integration): void {}
}
