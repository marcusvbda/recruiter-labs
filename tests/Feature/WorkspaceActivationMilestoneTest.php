<?php

use App\Actions\ConfirmJobCriteria;
use App\Actions\RecordCompanyMilestone;
use App\Actions\ReplaceApplicationFitAnalysis;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CompanyMilestone;
use App\Enums\JobCriteriaProcessingStatus;
use App\Events\WorkspaceMilestoneReached;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyMilestone as CompanyMilestoneRecord;
use App\Models\Job;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Activation is a claim about what a workspace really did, so these tests drive
 * the actual product actions — creating a job, confirming criteria, persisting
 * an evaluation — rather than writing ledger rows by hand. A milestone that only
 * appears when a test inserts it would prove nothing about the feature.
 */
beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

function milestoneRecorder(): RecordCompanyMilestone
{
    return app(RecordCompanyMilestone::class);
}

/** The milestones a workspace has reached, as plain values, oldest first. */
function reachedMilestones(Company $company): array
{
    return CompanyMilestoneRecord::query()
        ->where('company_id', $company->getKey())
        ->orderBy('achieved_at')
        ->orderBy('id')
        ->pluck('milestone')
        ->map(fn (CompanyMilestone $milestone): string => $milestone->value)
        ->all();
}

function milestoneReachedAt(Company $company, CompanyMilestone $milestone): ?string
{
    $achievedAt = CompanyMilestoneRecord::query()
        ->where('company_id', $company->getKey())
        ->where('milestone', $milestone->value)
        ->value('achieved_at');

    return $achievedAt === null ? null : CarbonImmutable::parse($achievedAt)->toDateTimeString();
}

/** A workspace member — the human every criteria confirmation requires. */
function activationRecruiter(Company $company): User
{
    $recruiter = User::factory()->create();
    $recruiter->companies()->attach($company);

    return $recruiter;
}

/**
 * A structurally valid evaluation response for every current criterion of the
 * job, so persistence exercises the real action instead of a stub.
 *
 * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
 */
function evaluationPayloadFor(Job $job): array
{
    $job->load('jobCriteria');

    $scores = $job->jobCriteria
        ->map(fn ($criterion): array => [
            'criterion_id' => (int) $criterion->getKey(),
            'score' => 80,
            'reason' => 'Directly evidenced in the application.',
            'confidence' => 'high',
            'evidence' => [['source' => 'resume', 'detail' => 'Listed under experience.']],
        ])
        ->values()
        ->all();

    return [$scores, []];
}

test('creating a workspace records the first milestone with no help from onboarding', function (): void {
    $company = Company::factory()->create();

    expect(reachedMilestones($company))->toBe([CompanyMilestone::WorkspaceCreated->value]);
});

test('the real product actions record each primary milestone, and only then', function (): void {
    $company = Company::factory()->create();
    $recruiter = activationRecruiter($company);

    $job = Job::factory()->withCriteriaAwaitingReview([
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
    ])->create(['company_id' => $company->getKey()]);

    expect(reachedMilestones($company))->toBe([
        CompanyMilestone::WorkspaceCreated->value,
        CompanyMilestone::FirstJobCreated->value,
    ]);

    // Deliberately before the criteria are confirmed: an application can arrive
    // while hiring intent is still being agreed, and setup must not be claimed.
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ]);

    expect(reachedMilestones($company))->toContain(CompanyMilestone::FirstApplicationCreated->value)
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceSetupCompleted->value);

    expect(app(ConfirmJobCriteria::class)->handle($job->fresh(), $recruiter))->toBeTrue();

    expect(reachedMilestones($company))->toContain(CompanyMilestone::FirstCriteriaConfirmed->value)
        // Setup is the composite of first job + confirmed criteria, and this is
        // the moment both are finally true.
        ->and(reachedMilestones($company))->toContain(CompanyMilestone::WorkspaceSetupCompleted->value)
        // The evaluation has not run, so the workspace is set up, not activated.
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceActivated->value);

    $job->refresh();
    [$scores, $brief] = evaluationPayloadFor($job);
    $application->refresh();
    $application->setRelation('job', $job);
    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::Processing,
        'analysis_generation' => 1,
    ])->save();

    $persisted = app(ReplaceApplicationFitAnalysis::class)
        ->handle($application, $scores, $brief, 1, (int) $job->criteria_generation);

    expect($persisted)->toBeTrue()
        ->and(reachedMilestones($company))->toContain(CompanyMilestone::FirstApplicationEvaluated->value)
        ->and(reachedMilestones($company))->toContain(CompanyMilestone::WorkspaceActivated->value);
});

test('setup completion needs a confirmed criteria revision, not merely stored criteria', function (): void {
    $company = Company::factory()->create();

    // Criteria exist and are editable, but no human has signed off on them —
    // exactly the state every pre-existing workspace was pushed into.
    Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);

    expect(reachedMilestones($company))->toBe([
        CompanyMilestone::WorkspaceCreated->value,
        CompanyMilestone::FirstJobCreated->value,
    ])->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceSetupCompleted->value);
});

test('a confirmation that confirmed nothing records nothing', function (): void {
    $company = Company::factory()->create();
    $recruiter = activationRecruiter($company);

    // No criteria stored at all, so there is nothing to confirm.
    $job = Job::factory()->create([
        'company_id' => $company->getKey(),
        'criteria_processing_status' => JobCriteriaProcessingStatus::NotStarted,
    ]);

    expect(app(ConfirmJobCriteria::class)->handle($job, $recruiter))->toBeFalse()
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::FirstCriteriaConfirmed->value)
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceSetupCompleted->value);
});

test('activation needs setup, an application and a successful evaluation together', function (): void {
    $company = Company::factory()->create();
    $recorder = milestoneRecorder();

    // Every prerequisite except the evaluation.
    $recorder->handle($company, CompanyMilestone::FirstJobCreated);
    $recorder->handle($company, CompanyMilestone::FirstCriteriaConfirmed);
    $recorder->handle($company, CompanyMilestone::FirstApplicationCreated);

    expect(reachedMilestones($company))->toContain(CompanyMilestone::WorkspaceSetupCompleted->value)
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceActivated->value);

    $recorder->handle($company, CompanyMilestone::FirstApplicationEvaluated);

    expect(reachedMilestones($company))->toContain(CompanyMilestone::WorkspaceActivated->value);
});

test('an evaluation without setup never activates the workspace', function (): void {
    $company = Company::factory()->create();
    $recorder = milestoneRecorder();

    $recorder->handle($company, CompanyMilestone::FirstApplicationCreated);
    $recorder->handle($company, CompanyMilestone::FirstApplicationEvaluated);

    expect(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceSetupCompleted->value)
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceActivated->value);
});

test('a failed evaluation does not complete the evaluation milestone', function (): void {
    $company = Company::factory()->create();
    $recruiter = activationRecruiter($company);
    $job = Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
    ]);
    app(ConfirmJobCriteria::class)->handle($job->fresh(), $recruiter);

    $application->forceFill(['analysis_status' => ApplicationAnalysisStatus::Failed])->save();

    expect(reachedMilestones($company))->not->toContain(CompanyMilestone::FirstApplicationEvaluated->value)
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceActivated->value);
});

test('an answer discarded because the criteria revision moved on is not an evaluation', function (): void {
    $company = Company::factory()->create();
    $recruiter = activationRecruiter($company);
    $job = Job::factory()->withCriteriaAwaitingReview([
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
    ])->create(['company_id' => $company->getKey()]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
    ]);
    app(ConfirmJobCriteria::class)->handle($job->fresh(), $recruiter);

    $job->refresh();
    [$scores, $brief] = evaluationPayloadFor($job);
    $application->refresh();
    $application->setRelation('job', $job);
    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::Processing,
        'analysis_generation' => 1,
    ])->save();

    // The provider genuinely answered, but for a revision the job has moved on
    // from, so the answer is thrown away rather than becoming an evaluation.
    $persisted = app(ReplaceApplicationFitAnalysis::class)
        ->handle($application, $scores, $brief, 1, (int) $job->criteria_generation - 1);

    expect($persisted)->toBeFalse()
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::FirstApplicationEvaluated->value)
        ->and(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceActivated->value);
});

test('reporting the same milestone repeatedly records one row and emits one event', function (): void {
    $company = Company::factory()->create();

    // Faked only now: creating the workspace legitimately reaches its own first
    // milestone, and this test is about what repeated reports of one milestone do.
    Event::fake([WorkspaceMilestoneReached::class]);

    $recorder = milestoneRecorder();

    $first = $recorder->handle($company, CompanyMilestone::FirstJobCreated);
    $second = $recorder->handle($company, CompanyMilestone::FirstJobCreated);
    $third = $recorder->handle($company, CompanyMilestone::FirstJobCreated);

    expect($first)->toBeTrue()
        // Only the call that actually inserted reports having done so, so a
        // repeated report cannot be mistaken for a second first-time milestone.
        ->and($second)->toBeFalse()
        ->and($third)->toBeFalse()
        ->and(CompanyMilestoneRecord::query()
            ->where('company_id', $company->getKey())
            ->where('milestone', CompanyMilestone::FirstJobCreated->value)
            ->count())->toBe(1);

    Event::assertDispatchedTimes(WorkspaceMilestoneReached::class, 1);
});

test('a repeated report cannot rewrite when a milestone was reached', function (): void {
    $company = Company::factory()->create();
    $recorder = milestoneRecorder();
    $original = CarbonImmutable::parse('2026-02-01 10:00:00');

    $recorder->handle($company, CompanyMilestone::FirstJobCreated, $original);
    $recorder->handle($company, CompanyMilestone::FirstJobCreated, CarbonImmutable::parse('2026-09-01 10:00:00'));

    expect(milestoneReachedAt($company, CompanyMilestone::FirstJobCreated))
        ->toBe($original->toDateTimeString());
});

test('composite milestones cannot be recorded directly', function (): void {
    $company = Company::factory()->create();
    $recorder = milestoneRecorder();

    // The guarantee is enforced, not merely documented: no caller may declare a
    // workspace set up or activated without the underlying activity.
    expect(fn () => $recorder->handle($company, CompanyMilestone::WorkspaceSetupCompleted))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $recorder->handle($company, CompanyMilestone::WorkspaceActivated))
        ->toThrow(InvalidArgumentException::class);

    expect(reachedMilestones($company))->toBe([CompanyMilestone::WorkspaceCreated->value]);
});

test('a composite is dated at the later of the milestones that complete it', function (): void {
    $company = Company::factory()->create();
    $recorder = milestoneRecorder();

    $recorder->handle($company, CompanyMilestone::FirstCriteriaConfirmed, CarbonImmutable::parse('2026-06-01 10:00:00'));

    expect(reachedMilestones($company))->not->toContain(CompanyMilestone::WorkspaceSetupCompleted->value);

    // Reported out of order: the job milestone arrives last and is what finally
    // completes setup, so setup is dated then — not at the earlier confirmation.
    $recorder->handle($company, CompanyMilestone::FirstJobCreated, CarbonImmutable::parse('2026-07-01 10:00:00'));

    expect(milestoneReachedAt($company, CompanyMilestone::WorkspaceSetupCompleted))
        ->toBe('2026-07-01 10:00:00');
});

test('milestone history survives the records that produced it changing or being deleted', function (): void {
    $company = Company::factory()->create();
    $recruiter = activationRecruiter($company);
    $job = Job::factory()->withCriteriaAwaitingReview([
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
    ])->create(['company_id' => $company->getKey(), 'published' => true]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
    ]);
    app(ConfirmJobCriteria::class)->handle($job->fresh(), $recruiter);

    $job->refresh();
    [$scores, $brief] = evaluationPayloadFor($job);
    $application->refresh();
    $application->setRelation('job', $job);
    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::Processing,
        'analysis_generation' => 1,
    ])->save();
    app(ReplaceApplicationFitAnalysis::class)
        ->handle($application, $scores, $brief, 1, (int) $job->criteria_generation);

    $before = reachedMilestones($company);
    expect($before)->toContain(CompanyMilestone::WorkspaceActivated->value);

    // The job is unpublished and its confirmation withdrawn, and the first
    // application is deleted outright. Onboarding measures whether a workspace
    // has ever reached a milestone, not whether it is still true today.
    $job->forceFill([
        'published' => false,
        'criteria_processing_status' => JobCriteriaProcessingStatus::AwaitingReview,
        'criteria_confirmed_generation' => null,
        'criteria_confirmed_at' => null,
    ])->save();
    $application->delete();

    expect(reachedMilestones($company))->toBe($before);
});

test('workspaces reach milestones independently of each other', function (): void {
    $company = Company::factory()->create();
    $other = Company::factory()->create();

    Job::factory()->create(['company_id' => $company->getKey()]);

    expect(reachedMilestones($company))->toBe([
        CompanyMilestone::WorkspaceCreated->value,
        CompanyMilestone::FirstJobCreated->value,
    ])->and(reachedMilestones($other))->toBe([CompanyMilestone::WorkspaceCreated->value]);
});

test('the backfill gives existing workspaces credit and re-running it changes nothing', function (): void {
    $activated = Company::factory()->create(['created_at' => CarbonImmutable::parse('2026-01-01 09:00:00')]);
    $setupOnly = Company::factory()->create(['created_at' => CarbonImmutable::parse('2026-02-01 09:00:00')]);
    $jobOnly = Company::factory()->create(['created_at' => CarbonImmutable::parse('2026-03-01 09:00:00')]);
    $bare = Company::factory()->create(['created_at' => CarbonImmutable::parse('2026-04-01 09:00:00')]);

    $activatedJob = Job::factory()->create([
        'company_id' => $activated->getKey(),
        'created_at' => CarbonImmutable::parse('2026-01-02 09:00:00'),
        'criteria_confirmed_at' => CarbonImmutable::parse('2026-01-03 09:00:00'),
    ]);
    Application::factory()->create([
        'company_id' => $activated->getKey(),
        'job_id' => $activatedJob->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'created_at' => CarbonImmutable::parse('2026-01-04 09:00:00'),
        'analyzed_at' => CarbonImmutable::parse('2026-01-07 09:00:00'),
    ]);

    $setupJob = Job::factory()->create([
        'company_id' => $setupOnly->getKey(),
        'created_at' => CarbonImmutable::parse('2026-02-02 09:00:00'),
        'criteria_confirmed_at' => CarbonImmutable::parse('2026-02-03 09:00:00'),
    ]);
    Application::factory()->create([
        'company_id' => $setupOnly->getKey(),
        'job_id' => $setupJob->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Pending,
        'created_at' => CarbonImmutable::parse('2026-02-04 09:00:00'),
    ]);

    // A job whose criteria were never human-confirmed: no confirmation, so no
    // setup, however much other activity exists.
    Job::factory()->create([
        'company_id' => $jobOnly->getKey(),
        'created_at' => CarbonImmutable::parse('2026-03-02 09:00:00'),
        'criteria_confirmed_at' => null,
    ]);

    // Simulate the pre-feature world: the activity above exists, the ledger does
    // not, because the runtime hooks did not exist when it happened.
    CompanyMilestoneRecord::query()->delete();

    $backfill = require database_path('migrations/2026_08_27_100100_backfill_company_milestones_from_existing_activity.php');
    $backfill->up();

    expect(reachedMilestones($activated))->toEqualCanonicalizing([
        CompanyMilestone::WorkspaceCreated->value,
        CompanyMilestone::FirstJobCreated->value,
        CompanyMilestone::FirstCriteriaConfirmed->value,
        CompanyMilestone::FirstApplicationCreated->value,
        CompanyMilestone::FirstApplicationEvaluated->value,
        CompanyMilestone::WorkspaceSetupCompleted->value,
        CompanyMilestone::WorkspaceActivated->value,
    ])
        // Dated from the real activity, never from when the migration ran.
        ->and(milestoneReachedAt($activated, CompanyMilestone::WorkspaceCreated))->toBe('2026-01-01 09:00:00')
        ->and(milestoneReachedAt($activated, CompanyMilestone::WorkspaceSetupCompleted))->toBe('2026-01-03 09:00:00')
        ->and(milestoneReachedAt($activated, CompanyMilestone::WorkspaceActivated))->toBe('2026-01-07 09:00:00');

    expect(reachedMilestones($setupOnly))->toEqualCanonicalizing([
        CompanyMilestone::WorkspaceCreated->value,
        CompanyMilestone::FirstJobCreated->value,
        CompanyMilestone::FirstCriteriaConfirmed->value,
        CompanyMilestone::FirstApplicationCreated->value,
        CompanyMilestone::WorkspaceSetupCompleted->value,
    ])->and(milestoneReachedAt($setupOnly, CompanyMilestone::WorkspaceSetupCompleted))->toBe('2026-02-03 09:00:00');

    expect(reachedMilestones($jobOnly))->toEqualCanonicalizing([
        CompanyMilestone::WorkspaceCreated->value,
        CompanyMilestone::FirstJobCreated->value,
    ]);

    expect(reachedMilestones($bare))->toBe([CompanyMilestone::WorkspaceCreated->value]);

    $snapshot = CompanyMilestoneRecord::query()
        ->orderBy('company_id')
        ->orderBy('milestone')
        ->get(['company_id', 'milestone', 'achieved_at'])
        ->toArray();

    $backfill->up();

    expect(CompanyMilestoneRecord::query()
        ->orderBy('company_id')
        ->orderBy('milestone')
        ->get(['company_id', 'milestone', 'achieved_at'])
        ->toArray())->toEqual($snapshot);
});

test('the backfill leaves a milestone the product already recorded untouched', function (): void {
    $company = Company::factory()->create(['created_at' => CarbonImmutable::parse('2026-01-01 09:00:00')]);
    Job::factory()->create([
        'company_id' => $company->getKey(),
        'created_at' => CarbonImmutable::parse('2026-05-05 09:00:00'),
    ]);

    $recordedAt = milestoneReachedAt($company, CompanyMilestone::FirstJobCreated);

    $backfill = require database_path('migrations/2026_08_27_100100_backfill_company_milestones_from_existing_activity.php');
    $backfill->up();

    expect(milestoneReachedAt($company, CompanyMilestone::FirstJobCreated))->toBe($recordedAt);
});
