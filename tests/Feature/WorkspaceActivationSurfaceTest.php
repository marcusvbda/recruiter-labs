<?php

use App\Actions\RecordCompanyMilestone;
use App\Enums\CompanyMilestone;
use App\Enums\CompanyRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Company;
use App\Models\CompanyMilestone as CompanyMilestoneRecord;
use App\Models\Job;
use App\Models\Plan;
use App\Models\User;
use App\Services\WorkspaceActivationJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * These render the real Overview, because the activation experience is the
 * feature: a ledger that is correct behind a page that throws, or that keeps
 * nagging an activated workspace, has not shipped anything.
 */
beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

/** A workspace with an Owner, plus a Member who also has access. */
function activationWorkspace(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company->users()->attach($owner, ['role' => CompanyRole::Owner->value]);
    $company->users()->attach($member, ['role' => CompanyRole::Member->value]);

    return [$company, $owner, $member];
}

/** Drives the workspace all the way to activated through the single ledger writer. */
function activateWorkspace(Company $company): void
{
    $recorder = app(RecordCompanyMilestone::class);

    foreach ([
        CompanyMilestone::FirstJobCreated,
        CompanyMilestone::FirstCriteriaConfirmed,
        CompanyMilestone::FirstApplicationCreated,
        CompanyMilestone::FirstApplicationEvaluated,
    ] as $milestone) {
        $recorder->handle($company, $milestone);
    }
}

function journeyFor(Company $company, User $user)
{
    return app(WorkspaceActivationJourney::class)->for($company, $user);
}

/** The milestones a workspace has reached, as plain values. */
function reachedMilestoneValues(Company $company): array
{
    return CompanyMilestoneRecord::query()
        ->where('company_id', $company->getKey())
        ->orderBy('id')
        ->pluck('milestone')
        ->map(fn (CompanyMilestone $milestone): string => $milestone->value)
        ->all();
}

test('an unactivated workspace shows the checklist, the welcome and the launcher', function (): void {
    [$company, $owner] = activationWorkspace();
    Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);

    $this->actingAs($owner)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertSee(__('onboarding.checklist.heading'))
        // The next useful action, and the value of taking it.
        ->assertSee(__('onboarding.checklist.steps.confirm_hiring_criteria.action'))
        ->assertSee(__('onboarding.checklist.optional_heading'))
        ->assertSee(__('onboarding.welcome.heading'))
        ->assertSee(__('onboarding.welcome.continue_later'))
        ->assertSee(__('onboarding.launcher.label'));
});

test('an activated workspace shows no onboarding surface but keeps its Overview', function (): void {
    [$company, $owner] = activationWorkspace();
    activateWorkspace($company);

    $this->actingAs($owner)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertDontSee(__('onboarding.checklist.heading'))
        ->assertDontSee(__('onboarding.welcome.heading'))
        ->assertDontSee(__('onboarding.launcher.label'))
        // The Overview itself is untouched: activation removes the guidance, not
        // the operational page.
        ->assertSee(__('attention.heading'));
});

test('the launcher follows the user around the workspace, and stops once activated', function (): void {
    [$company, $owner] = activationWorkspace();
    Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);

    // The point of the launcher is access to the journey away from the Overview,
    // so it has to be there on an ordinary recruitment page too.
    $this->actingAs($owner)
        ->get(JobResource::getUrl('index', tenant: $company))
        ->assertOk()
        ->assertSee(__('onboarding.launcher.label'));

    activateWorkspace($company);

    $this->actingAs($owner)
        ->get(JobResource::getUrl('index', tenant: $company))
        ->assertOk()
        ->assertDontSee(__('onboarding.launcher.label'));
});

test('every authorized member sees the same shared workspace progress', function (): void {
    [$company, $owner, $member] = activationWorkspace();
    app(RecordCompanyMilestone::class)->handle($company, CompanyMilestone::FirstJobCreated);

    $ownerProgress = journeyFor($company->fresh(), $owner);
    $memberProgress = journeyFor($company->fresh(), $member);

    expect($memberProgress->percentage())->toBe($ownerProgress->percentage())
        ->and($memberProgress->completedCount())->toBe($ownerProgress->completedCount())
        ->and($memberProgress->nextStep()['key'])->toBe($ownerProgress->nextStep()['key'])
        ->and($memberProgress->isSetupComplete())->toBe($ownerProgress->isSetupComplete())
        ->and($memberProgress->isActivated())->toBe($ownerProgress->isActivated())
        // Progress belongs to the workspace, so one member's view of the primary
        // steps is identical to another's.
        ->and(array_column($memberProgress->primarySteps, 'is_complete'))
        ->toBe(array_column($ownerProgress->primarySteps, 'is_complete'));

    // A member who joins later starts from the workspace's existing progress,
    // never from zero.
    $joiner = User::factory()->create();
    $company->users()->attach($joiner, ['role' => CompanyRole::Member->value]);

    expect(journeyFor($company->fresh(), $joiner)->completedCount())
        ->toBe($ownerProgress->completedCount());
});

test('an optional step is only actionable for a user who may perform it', function (): void {
    [$company, $owner, $member] = activationWorkspace();

    $optionalFor = fn (User $user): array => collect(journeyFor($company->fresh(), $user)->optionalSteps)
        ->keyBy('key')
        ->map(fn (array $step): bool => $step['is_actionable'])
        ->all();

    // Managing membership is an ownership decision, so a Member must not be
    // handed an invitation CTA that the real page would refuse them.
    expect($optionalFor($member)['invite_teammate'])->toBeFalse()
        ->and($optionalFor($owner)['invite_teammate'])->toBeTrue();
});

test('optional setup never counts toward progress, setup or activation', function (): void {
    [$company, $owner] = activationWorkspace();

    $progress = journeyFor($company->fresh(), $owner);

    // Inviting a teammate is already done here — the workspace has two members —
    // and it still moves nothing: only the workspace milestones do.
    expect(collect($progress->optionalSteps)->firstWhere('key', 'invite_teammate')['is_done'])->toBeTrue()
        ->and($progress->totalCount())->toBe(5)
        ->and($progress->completedCount())->toBe(1)
        ->and($progress->percentage())->toBe(20)
        ->and($progress->isSetupComplete())->toBeFalse()
        ->and($progress->isActivated())->toBeFalse();
});

test('each workspace shows only its own progress, through one service instance', function (): void {
    [$company, $owner] = activationWorkspace();
    $other = Company::factory()->create();
    $other->users()->attach($owner, ['role' => CompanyRole::Owner->value]);

    app(RecordCompanyMilestone::class)->handle($company, CompanyMilestone::FirstJobCreated);
    Job::factory()->create(['company_id' => $other->getKey()]);
    app(RecordCompanyMilestone::class)->handle($other, CompanyMilestone::FirstCriteriaConfirmed);

    // Deliberately the same instance for both workspaces: switching workspaces
    // must not let one answer for the other.
    $journey = app(WorkspaceActivationJourney::class);
    $first = $journey->for($company->fresh(), $owner);
    $second = $journey->for($other->fresh(), $owner);

    expect($first->isSetupComplete())->toBeFalse()
        ->and($second->isSetupComplete())->toBeTrue()
        ->and($first->nextStep()['key'])->toBe('confirm_hiring_criteria')
        ->and($second->nextStep()['key'])->toBe('add_first_application')
        // Each workspace's CTA leads into that workspace, never the other one.
        ->and($first->nextStep()['url'])->toContain($company->slug)
        ->and($second->nextStep()['url'])->toContain($other->slug)
        ->and($first->nextStep()['url'])->not->toContain($other->slug);
});

test('a member whose workspace access is disabled cannot reach the onboarding surfaces', function (): void {
    [$company, , $member] = activationWorkspace();
    app(RecordCompanyMilestone::class)->handle($company, CompanyMilestone::FirstJobCreated);

    $company->users()->updateExistingPivot($member, ['access_disabled_at' => now()]);

    expect($company->hasWorkspaceAccess($member))->toBeFalse();

    // Tenant resolution refuses the request outright: onboarding is not a second
    // way into a workspace someone may not enter.
    $this->actingAs($member)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertNotFound();
});

test('someone who does not belong to the workspace cannot reach its onboarding', function (): void {
    [$company] = activationWorkspace();
    app(RecordCompanyMilestone::class)->handle($company, CompanyMilestone::FirstJobCreated);

    $this->actingAs(User::factory()->create())
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertNotFound();
});

test('dismissing the welcome is personal presentation state and changes no progress', function (): void {
    [$company, $owner, $member] = activationWorkspace();
    Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);

    $before = reachedMilestoneValues($company);

    $company->dismissOnboardingWelcomeFor($owner);

    expect($company->hasDismissedOnboardingWelcome($owner))->toBeTrue()
        // Personal: the same workspace still greets a colleague who has not
        // dismissed it.
        ->and($company->hasDismissedOnboardingWelcome($member))->toBeFalse()
        // And the shared activation state is untouched.
        ->and(reachedMilestoneValues($company))->toBe($before)
        ->and(journeyFor($company->fresh(), $owner)->completedCount())
        ->toBe(journeyFor($company->fresh(), $member)->completedCount());

    $this->actingAs($owner)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertDontSee(__('onboarding.welcome.heading'))
        // Dismissing the introduction does not remove the journey itself.
        ->assertSee(__('onboarding.checklist.heading'));

    $this->actingAs($member)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertSee(__('onboarding.welcome.heading'));
});

test('hiding the launcher is personal presentation state and changes no progress', function (): void {
    [$company, $owner, $member] = activationWorkspace();
    Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);

    $before = reachedMilestoneValues($company);

    $company->hideOnboardingLauncherFor($owner);

    expect($company->hasHiddenOnboardingLauncher($owner))->toBeTrue()
        ->and($company->hasHiddenOnboardingLauncher($member))->toBeFalse()
        ->and(reachedMilestoneValues($company))->toBe($before);

    $this->actingAs($owner)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertDontSee(__('onboarding.launcher.label'))
        ->assertSee(__('onboarding.checklist.heading'));

    $this->actingAs($member)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertSee(__('onboarding.launcher.label'));
});

test('the checklist follows the interface language without changing progress', function (): void {
    [$company, $owner] = activationWorkspace();
    Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);

    $englishProgress = journeyFor($company->fresh(), $owner)->completedCount();

    $owner->forceFill(['locale' => 'pt_BR'])->save();

    $this->actingAs($owner)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertOk()
        ->assertSee(__('onboarding.checklist.heading', locale: 'pt_BR'));

    // Same workspace, same ledger: language is presentation, not progress.
    expect(journeyFor($company->fresh(), $owner)->completedCount())->toBe($englishProgress);
});
