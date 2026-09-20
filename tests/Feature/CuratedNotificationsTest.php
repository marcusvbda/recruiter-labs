<?php

use App\Actions\ReplaceApplicationFitAnalysis;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\CandidateImportStatus;
use App\Enums\CompanyRole;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\CandidateImportBatch;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\User;
use App\Services\RecruiterNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

/** @return array{Company, User} */
function notificationWorkspace(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->create();
    $company->users()->attach($owner, ['role' => CompanyRole::Owner->value]);

    return [$company, $owner];
}

/** @return Collection<int, DatabaseNotification> */
function notificationsOf(User $user): Collection
{
    return DatabaseNotification::query()
        ->where('notifiable_type', $user->getMorphClass())
        ->where('notifiable_id', $user->getKey())
        ->get();
}

it('notifies the uploader once when an import finishes with something to open', function (): void {
    [$company, $owner] = notificationWorkspace();
    $stranger = User::factory()->create();
    Company::factory()->create()->users()->attach($stranger, ['role' => CompanyRole::Owner->value]);

    $batch = CandidateImportBatch::query()->create([
        'company_id' => $company->getKey(),
        'uploaded_by_id' => $owner->getKey(),
        'source_label' => 'candidates.csv',
        'status' => CandidateImportStatus::Processing,
        'expires_at' => now()->addDays(7),
    ]);

    app(RecruiterNotifier::class)->candidateImportCompleted($batch, imported: 4, unresolved: 1);

    $notifications = notificationsOf($owner);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['viewData']['kind'])->toBe(RecruiterNotifier::KindImportCompleted)
        ->and($notifications->first()->data['actions'][0]['url'] ?? null)->toBeString()
        ->and($notifications->first()->data['actions'][0]['url'])->not->toBe('');

    // Tenant safety: the workspace next door never hears about this import.
    expect(notificationsOf($stranger))->toHaveCount(0);
});

it('stays silent for an import that produced nothing to look at, and speaks once for a failed one', function (): void {
    [$company, $owner] = notificationWorkspace();

    $batch = CandidateImportBatch::query()->create([
        'company_id' => $company->getKey(),
        'uploaded_by_id' => $owner->getKey(),
        'source_label' => 'candidates.csv',
        'status' => CandidateImportStatus::Processing,
        'expires_at' => now()->addDays(7),
    ]);

    app(RecruiterNotifier::class)->candidateImportCompleted($batch, imported: 0, unresolved: 0);

    expect(notificationsOf($owner))->toHaveCount(0);

    app(RecruiterNotifier::class)->candidateImportFailed($batch);

    $notifications = notificationsOf($owner);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['viewData']['kind'])->toBe(RecruiterNotifier::KindImportFailed);
});

it('notifies once when a candidate email fails and never for one that is sent', function (): void {
    [$company, $owner] = notificationWorkspace();
    $candidate = Candidate::factory()->create(['company_id' => $company->getKey()]);
    $thread = CandidateCommunicationThread::query()->create([
        'company_id' => $company->getKey(),
        'candidate_id' => $candidate->getKey(),
    ]);

    $sent = CandidateCommunicationMessage::query()->create([
        'company_id' => $company->getKey(),
        'thread_id' => $thread->getKey(),
        'status' => CandidateCommunicationMessageStatus::Queued,
    ]);
    $sent->forceFill(['status' => CandidateCommunicationMessageStatus::Sent])->save();

    expect(notificationsOf($owner))->toHaveCount(0);

    $failing = CandidateCommunicationMessage::query()->create([
        'company_id' => $company->getKey(),
        'thread_id' => $thread->getKey(),
        'status' => CandidateCommunicationMessageStatus::Queued,
        'authorized_by_id' => $owner->getKey(),
    ]);
    $failing->forceFill(['status' => CandidateCommunicationMessageStatus::Failed])->save();

    $notifications = notificationsOf($owner);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['viewData']['kind'])->toBe(RecruiterNotifier::KindCommunicationFailed);

    // Re-saving a record that already failed is not a second failure.
    $failing->forceFill(['status' => CandidateCommunicationMessageStatus::Failed])->save();
    $failing->touch();

    expect(notificationsOf($owner))->toHaveCount(1);
});

it('announces an exhausted AI allowance once while nobody has read it', function (): void {
    [$company, $owner] = notificationWorkspace();

    app(RecruiterNotifier::class)->aiAllowanceBlocked($company);
    app(RecruiterNotifier::class)->aiAllowanceBlocked($company);

    expect(notificationsOf($owner))->toHaveCount(1);

    // Reading it acknowledges the situation, so the next block is told again.
    notificationsOf($owner)->first()->markAsRead();
    app(RecruiterNotifier::class)->aiAllowanceBlocked($company);

    expect(notificationsOf($owner))->toHaveCount(2);
});

it('does not notify for a routine candidate evaluation completing', function (): void {
    [$company, $owner] = notificationWorkspace();

    $job = Job::factory()->withConfirmedCriteria([
        ['criterion' => '3+ years Laravel', 'weight' => 6],
    ])->create(['company_id' => $company->getKey()]);

    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Processing,
        'analysis_generation' => 1,
    ]);

    $criterion = $job->jobCriteria()->sole();

    $persisted = app(ReplaceApplicationFitAnalysis::class)->handle(
        $application,
        [[
            'criterion_id' => (int) $criterion->getKey(),
            'score' => 88,
            'reason' => 'The application describes concrete work here.',
            'confidence' => 'high',
            'evidence' => [['source' => 'resume', 'detail' => 'Laravel 11 payments API.']],
        ]],
        [],
        1,
        (int) $job->criteria_generation,
    );

    expect($persisted)->toBeTrue()
        ->and($application->fresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        // A candidate being evaluated is routine work, not an interruption.
        ->and(notificationsOf($owner))->toHaveCount(0);
});
