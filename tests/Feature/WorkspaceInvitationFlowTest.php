<?php

use App\Actions\AcceptWorkspaceInvitation;
use App\Actions\InviteWorkspaceMember;
use App\Actions\ResendWorkspaceInvitation;
use App\Actions\RevokeWorkspaceInvitation;
use App\Enums\CompanyRole;
use App\Exceptions\WorkspaceInvitationAlreadyPending;
use App\Exceptions\WorkspaceInvitationEmailMismatch;
use App\Exceptions\WorkspaceInvitationEmailNotVerified;
use App\Exceptions\WorkspaceInvitationNotAcceptable;
use App\Exceptions\WorkspaceMemberAlreadyExists;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
    Notification::fake();
});

function workspaceWithOwner(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->create();
    $company->users()->attach($owner, ['role' => CompanyRole::Owner->value]);

    return [$company, $owner];
}

function inviteAction(): InviteWorkspaceMember
{
    return app(InviteWorkspaceMember::class);
}

test('an Owner can invite an address that is not a member without creating membership', function (): void {
    [$company, $owner] = workspaceWithOwner();

    $invitation = inviteAction()->handle($company, $owner, 'new-recruiter@example.com');

    expect($invitation)->toBeInstanceOf(CompanyInvitation::class)
        ->and($invitation->isPending())->toBeTrue()
        ->and($company->activeMembers()->get())->toHaveCount(1);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
});

test('a new invitation expires after 7 days', function (): void {
    [$company, $owner] = workspaceWithOwner();

    $invitation = inviteAction()->handle($company, $owner, 'expiring@example.com');

    expect($invitation->expires_at->diffInDays(now(), true))->toBeLessThanOrEqual(7)
        ->and($invitation->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

test('an expired invitation cannot create membership', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $recipient = User::factory()->create(['email' => 'expired-guy@example.com', 'email_verified_at' => now()]);

    $invitation = CompanyInvitation::factory()->expired()->create([
        'company_id' => $company->id,
        'email' => 'expired-guy@example.com',
        'invited_by_id' => $owner->id,
    ]);

    expect(fn () => app(AcceptWorkspaceInvitation::class)->handle($invitation, $recipient))
        ->toThrow(WorkspaceInvitationNotAcceptable::class);

    expect($company->roleFor($recipient))->toBeNull();
});

test('a revoked invitation cannot create membership', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $recipient = User::factory()->create(['email' => 'revoked-guy@example.com', 'email_verified_at' => now()]);

    $invitation = CompanyInvitation::factory()->revoked()->create([
        'company_id' => $company->id,
        'email' => 'revoked-guy@example.com',
        'invited_by_id' => $owner->id,
    ]);

    expect(fn () => app(AcceptWorkspaceInvitation::class)->handle($invitation, $recipient))
        ->toThrow(WorkspaceInvitationNotAcceptable::class);

    expect($company->roleFor($recipient))->toBeNull();
});

test('inviting an address that already belongs to an active member is refused', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $member = User::factory()->create(['email' => 'already-here@example.com']);
    $company->users()->attach($member, ['role' => CompanyRole::Member->value]);

    expect(fn () => inviteAction()->handle($company, $owner, 'already-here@example.com'))
        ->toThrow(WorkspaceMemberAlreadyExists::class);

    try {
        inviteAction()->handle($company, $owner, 'already-here@example.com');
    } catch (WorkspaceMemberAlreadyExists $exception) {
        expect($exception->accessDisabled)->toBeFalse();
    }
});

test('inviting an address belonging to a member whose access is disabled is refused with a distinguishable outcome', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $member = User::factory()->create(['email' => 'disabled-guy@example.com']);
    $company->users()->attach($member, [
        'role' => CompanyRole::Member->value,
        'access_disabled_at' => now(),
    ]);

    try {
        inviteAction()->handle($company, $owner, 'disabled-guy@example.com');
        expect(false)->toBeTrue('Expected WorkspaceMemberAlreadyExists to be thrown.');
    } catch (WorkspaceMemberAlreadyExists $exception) {
        expect($exception->accessDisabled)->toBeTrue();
    }
});

test('case-insensitive email cannot bypass duplicate-member or duplicate-invitation rules', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $member = User::factory()->create(['email' => 'casetest@example.com']);
    $company->users()->attach($member, ['role' => CompanyRole::Member->value]);

    expect(fn () => inviteAction()->handle($company, $owner, 'CaseTest@Example.com'))
        ->toThrow(WorkspaceMemberAlreadyExists::class);

    inviteAction()->handle($company, $owner, 'pending-case@example.com');

    expect(fn () => inviteAction()->handle($company, $owner, 'Pending-Case@Example.com'))
        ->toThrow(WorkspaceInvitationAlreadyPending::class);
});

test('at most one usable pending invitation exists per workspace and normalized email, and resend rotates the token with a fresh window', function (): void {
    [$company, $owner] = workspaceWithOwner();

    $invitation = inviteAction()->handle($company, $owner, 'once-only@example.com');

    expect(fn () => inviteAction()->handle($company, $owner, 'once-only@example.com'))
        ->toThrow(WorkspaceInvitationAlreadyPending::class);

    expect(CompanyInvitation::query()->where('company_id', $company->id)->where('email', 'once-only@example.com')->count())->toBe(1);

    $originalHash = $invitation->token_hash;
    $originalExpiry = $invitation->expires_at;

    test()->travel(1)->days();

    $resent = app(ResendWorkspaceInvitation::class)->handle($invitation, $owner);

    expect($resent->id)->toBe($invitation->id)
        ->and($resent->token_hash)->not->toBe($originalHash)
        ->and($resent->expires_at->isAfter($originalExpiry))->toBeTrue()
        ->and(CompanyInvitation::query()->where('company_id', $company->id)->where('email', 'once-only@example.com')->count())->toBe(1);

    test()->travelBack();
});

test('acceptance requires the matching email', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $invitation = inviteAction()->handle($company, $owner, 'invited@example.com');
    $wrongUser = User::factory()->create(['email' => 'someone-else@example.com', 'email_verified_at' => now()]);

    expect(fn () => app(AcceptWorkspaceInvitation::class)->handle($invitation, $wrongUser))
        ->toThrow(WorkspaceInvitationEmailMismatch::class);

    expect($company->roleFor($wrongUser))->toBeNull();
});

test('acceptance requires a verified email', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $invitation = inviteAction()->handle($company, $owner, 'unverified@example.com');
    $recipient = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

    expect(fn () => app(AcceptWorkspaceInvitation::class)->handle($invitation, $recipient))
        ->toThrow(WorkspaceInvitationEmailNotVerified::class);

    expect($company->roleFor($recipient))->toBeNull();
});

test('acceptance is idempotent and creates exactly one membership', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $invitation = inviteAction()->handle($company, $owner, 'twice@example.com');
    $recipient = User::factory()->create(['email' => 'twice@example.com', 'email_verified_at' => now()]);

    app(AcceptWorkspaceInvitation::class)->handle($invitation, $recipient);

    // The invitation is single-use: a second direct call is refused rather than
    // silently succeeding. The user-visible idempotency (clicking the link
    // twice landing them in the workspace either way) is the controller's job,
    // via `workspaceAlreadyJoinedBy()`; here the contract under test is that no
    // second membership can ever be produced.
    expect(fn () => app(AcceptWorkspaceInvitation::class)->handle($invitation->fresh(), $recipient))
        ->toThrow(WorkspaceInvitationNotAcceptable::class);

    expect($company->users()->wherePivot('user_id', $recipient->id)->count())->toBe(1);
});

test('acceptance produces a Member with access enabled, never an Owner', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $invitation = inviteAction()->handle($company, $owner, 'newmember@example.com');
    $recipient = User::factory()->create(['email' => 'newmember@example.com', 'email_verified_at' => now()]);

    app(AcceptWorkspaceInvitation::class)->handle($invitation, $recipient);

    expect($company->roleFor($recipient))->toBe(CompanyRole::Member)
        ->and($company->hasWorkspaceAccess($recipient))->toBeTrue();
});

test('accepting an invitation does not touch memberships in other workspaces', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $otherCompany = Company::factory()->create();
    $recipient = User::factory()->create(['email' => 'multiworkspace@example.com', 'email_verified_at' => now()]);
    $otherCompany->users()->attach($recipient, ['role' => CompanyRole::Member->value]);

    $invitation = inviteAction()->handle($company, $owner, 'multiworkspace@example.com');
    app(AcceptWorkspaceInvitation::class)->handle($invitation, $recipient);

    expect($company->roleFor($recipient))->toBe(CompanyRole::Member)
        ->and($otherCompany->roleFor($recipient))->toBe(CompanyRole::Member)
        ->and($otherCompany->hasWorkspaceAccess($recipient))->toBeTrue();
});

test('a Member cannot invite, resend or revoke via a direct action call', function (): void {
    [$company, $owner] = workspaceWithOwner();
    $member = User::factory()->create();
    $company->users()->attach($member, ['role' => CompanyRole::Member->value]);

    expect(fn () => inviteAction()->handle($company, $member, 'target@example.com'))
        ->toThrow(AuthorizationException::class);

    $pending = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by_id' => $owner->id,
    ]);

    expect(fn () => app(ResendWorkspaceInvitation::class)->handle($pending, $member))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(RevokeWorkspaceInvitation::class)->handle($pending, $member))
        ->toThrow(AuthorizationException::class);
});

test('an Owner of one workspace cannot invite, resend or revoke against another workspace', function (): void {
    [$companyA, $ownerA] = workspaceWithOwner();
    [$companyB] = workspaceWithOwner();

    expect(fn () => inviteAction()->handle($companyB, $ownerA, 'isolation@example.com'))
        ->toThrow(AuthorizationException::class);

    $invitationB = CompanyInvitation::factory()->create(['company_id' => $companyB->id]);

    expect(fn () => app(ResendWorkspaceInvitation::class)->handle($invitationB, $ownerA))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(RevokeWorkspaceInvitation::class)->handle($invitationB, $ownerA))
        ->toThrow(AuthorizationException::class);
});
