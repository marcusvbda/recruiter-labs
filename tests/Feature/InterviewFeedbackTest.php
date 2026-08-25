<?php

use App\Actions\RecordInterviewFeedback;
use App\Enums\AnalysisConfidence;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\InterviewFeedbackResult;
use App\Exceptions\InterviewFeedbackException;
use App\Models\Application;
use App\Models\Company;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\InterviewFeedbackCriterion;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function feedbackCompany(): Company
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    return Company::factory()->create();
}

/** A human who belongs to the workspace — what every submission requires. */
function feedbackInterviewer(Company $company): User
{
    $interviewer = User::factory()->create();
    $interviewer->companies()->attach($company);

    return $interviewer;
}

/** @param  list<array{criterion: string, weight: int}>  $criteria */
function feedbackJob(Company $company, array $criteria = []): Job
{
    return Job::factory()
        ->withConfirmedCriteria($criteria)
        ->create(['company_id' => $company->id]);
}

/** An interview whose slot has already passed. */
function heldInterview(Application $application): Interview
{
    return Interview::factory()->held()->create([
        'company_id' => $application->company_id,
        'application_id' => $application->id,
    ]);
}

function applicationForJob(Job $job): Application
{
    return Application::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
    ]);
}

/** @return list<JobCriterion> */
function jobCriteriaOf(Job $job): array
{
    return $job->jobCriteria()->orderBy('id')->get()->all();
}

function recordFeedback(): RecordInterviewFeedback
{
    return app(RecordInterviewFeedback::class);
}

test('an interviewer records what the interview established about every criterion', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company, [
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
        ['criterion' => 'Led a team of 5+ engineers', 'weight' => 8],
        ['criterion' => 'Event-driven architecture', 'weight' => 6],
        ['criterion' => 'Fluent written English', 'weight' => 4],
    ]);
    $application = applicationForJob($job);
    $interview = heldInterview($application);

    [$first, $second, $third, $fourth] = jobCriteriaOf($job);

    $feedback = recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $first->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'Walked through a queue refactor in detail.'],
        ['job_criterion_id' => $second->id, 'result' => InterviewFeedbackResult::PartiallyConfirmed, 'evidence_note' => 'Led three engineers, not five.'],
        ['job_criterion_id' => $third->id, 'result' => InterviewFeedbackResult::NotConfirmed, 'evidence_note' => 'Could not describe a consumer retry strategy.'],
        ['job_criterion_id' => $fourth->id, 'result' => InterviewFeedbackResult::NotAssessed, 'evidence_note' => null],
    ], 'Strong hands-on engineer, thinner on leadership.');

    expect($feedback->submitted_by_id)->toBe($interviewer->id)
        ->and($feedback->submittedBy->is($interviewer))->toBeTrue()
        ->and($feedback->interview_id)->toBe($interview->id)
        ->and($feedback->application_id)->toBe($application->id)
        ->and($feedback->job_id)->toBe($job->id)
        ->and($feedback->submitted_at)->not->toBeNull()
        ->and($feedback->general_note)->toBe('Strong hands-on engineer, thinner on leadership.');

    $results = $feedback->criteria()->get()->keyBy('job_criterion_id');

    expect($results)->toHaveCount(4)
        ->and($results[$first->id]->result)->toBe(InterviewFeedbackResult::Confirmed)
        ->and($results[$first->id]->evidence_note)->toBe('Walked through a queue refactor in detail.')
        ->and($results[$second->id]->result)->toBe(InterviewFeedbackResult::PartiallyConfirmed)
        ->and($results[$third->id]->result)->toBe(InterviewFeedbackResult::NotConfirmed)
        ->and($results[$fourth->id]->result)->toBe(InterviewFeedbackResult::NotAssessed);
});

test('the recorded criterion text and weight come from the job, not from the caller', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company, [
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
    ]);
    $application = applicationForJob($job);
    $interview = heldInterview($application);

    [$criterion] = jobCriteriaOf($job);

    $feedback = recordFeedback()->handle($interview, $interviewer, [
        [
            'job_criterion_id' => $criterion->id,
            'result' => InterviewFeedbackResult::Confirmed,
            'evidence_note' => 'Six years shipping Laravel.',
        ],
    ]);

    $stored = $feedback->criteria()->firstOrFail();

    expect($stored->criterion)->toBe('Production Laravel experience')
        ->and($stored->weight)->toBe(10)
        ->and($feedback->criteria_generation)->toBe($job->criteria_generation);

    // A later edit to the job is a later edit, not a retroactive rewrite of
    // what this interviewer was actually assessing.
    $criterion->forceFill([
        'criterion' => 'Production Laravel and Livewire experience',
        'weight' => 3,
    ])->save();
    $job->forceFill(['criteria_generation' => $job->criteria_generation + 1])->save();

    $stored->refresh();
    $feedback->refresh();

    expect($stored->criterion)->toBe('Production Laravel experience')
        ->and($stored->weight)->toBe(10)
        ->and($feedback->criteria_generation)->toBe(1);
});

test('not assessed is recorded as unresolved, never as a negative finding', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company, [
        ['criterion' => 'Fluent written English', 'weight' => 4],
        ['criterion' => 'Event-driven architecture', 'weight' => 6],
    ]);
    $application = applicationForJob($job);
    $interview = heldInterview($application);

    [$unassessed, $refuted] = jobCriteriaOf($job);

    $feedback = recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $unassessed->id, 'result' => InterviewFeedbackResult::NotAssessed, 'evidence_note' => '   Ran out of time before the writing sample.  '],
        ['job_criterion_id' => $refuted->id, 'result' => InterviewFeedbackResult::NotConfirmed, 'evidence_note' => 'No experience with message brokers.'],
    ]);

    $results = $feedback->criteria()->get()->keyBy('job_criterion_id');

    expect($results[$unassessed->id]->result)->toBe(InterviewFeedbackResult::NotAssessed)
        ->and($results[$unassessed->id]->result)->not->toBe($results[$refuted->id]->result)
        ->and($results[$unassessed->id]->result->isAssertion())->toBeFalse()
        ->and($results[$refuted->id]->result->isAssertion())->toBeTrue()
        // "We never got to it" still carries a reason worth reading later.
        ->and($results[$unassessed->id]->evidence_note)->toBe('Ran out of time before the writing sample.');
});

test('a cancelled interview cannot receive feedback', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $application = applicationForJob($job);

    $interview = Interview::factory()->held()->cancelled()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
    ]);

    [$criterion] = jobCriteriaOf($job);

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
    ]))->toThrow(
        InterviewFeedbackException::class,
        InterviewFeedbackException::interviewCancelled()->getMessage(),
    );

    expect(InterviewFeedback::query()->count())->toBe(0);
});

test('feedback is recorded whatever the interview timing is', function (string $timing, callable $schedule): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $application = applicationForJob($job);

    $interview = Interview::factory()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
        ...$schedule(),
    ]);

    [$criterion] = jobCriteriaOf($job);

    $feedback = recordFeedback()->handle($interview, $interviewer, [
        [
            'job_criterion_id' => $criterion->id,
            'result' => InterviewFeedbackResult::Confirmed,
            'evidence_note' => 'Noted while the conversation was still fresh.',
        ],
    ]);

    expect(InterviewFeedback::query()->count())->toBe(1)
        ->and($feedback->interview_id)->toBe($interview->id)
        ->and($feedback->submitted_by_id)->toBe($interviewer->id)
        ->and($feedback->criteria()->count())->toBe(1);

    $recorded = $feedback->criteria()->first();

    expect($recorded->job_criterion_id)->toBe($criterion->id)
        ->and($recorded->result)->toBe(InterviewFeedbackResult::Confirmed)
        ->and($recorded->evidence_note)->toBe('Noted while the conversation was still fresh.');
})->with([
    // Timing is not a gate: an interviewer may record before the slot, while it
    // is running, or after it ended.
    'upcoming' => ['upcoming', fn (): array => [
        'scheduled_at' => now()->addDays(2)->startOfHour(),
        'ends_at' => now()->addDays(2)->startOfHour()->addHour(),
    ]],
    'running right now' => ['running right now', fn (): array => [
        'scheduled_at' => now()->subMinutes(15),
        'ends_at' => now()->addMinutes(45),
    ]],
    'already ended' => ['already ended', fn (): array => [
        'scheduled_at' => now()->subDays(2)->startOfHour(),
        'ends_at' => now()->subDays(2)->startOfHour()->addHour(),
    ]],
]);

test('a criterion from another job or another workspace is refused and nothing is written', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $application = applicationForJob($job);
    $interview = heldInterview($application);

    [$ownCriterion] = jobCriteriaOf($job);
    [$otherJobCriterion] = jobCriteriaOf(feedbackJob($company));
    [$otherCompanyCriterion] = jobCriteriaOf(feedbackJob(feedbackCompany()));

    $message = InterviewFeedbackException::criterionOutsideInterviewedJob()->getMessage();

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $ownCriterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
        ['job_criterion_id' => $otherJobCriterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
    ]))->toThrow(InterviewFeedbackException::class, $message);

    expect(InterviewFeedback::query()->count())->toBe(0)
        ->and(InterviewFeedbackCriterion::query()->count())->toBe(0);

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $otherCompanyCriterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
    ]))->toThrow(InterviewFeedbackException::class, $message);

    expect(InterviewFeedback::query()->count())->toBe(0)
        ->and(InterviewFeedbackCriterion::query()->count())->toBe(0);
});

test('a submission with no criteria at all is refused', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $interview = heldInterview(applicationForJob(feedbackJob($company)));

    expect(fn () => recordFeedback()->handle($interview, $interviewer, []))
        ->toThrow(
            InterviewFeedbackException::class,
            InterviewFeedbackException::noCriteriaSubmitted()->getMessage(),
        );

    expect(InterviewFeedback::query()->count())->toBe(0);
});

test('the same criterion answered twice is refused instead of one answer winning', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $interview = heldInterview(applicationForJob($job));

    [$criterion] = jobCriteriaOf($job);

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::NotConfirmed, 'evidence_note' => null],
    ]))->toThrow(
        InterviewFeedbackException::class,
        InterviewFeedbackException::duplicateCriterion()->getMessage(),
    );

    expect(InterviewFeedback::query()->count())->toBe(0);
});

test('someone outside the workspace cannot record feedback for its interviews', function (): void {
    $company = feedbackCompany();
    $job = feedbackJob($company);
    $interview = heldInterview(applicationForJob($job));
    $outsider = User::factory()->create();

    [$criterion] = jobCriteriaOf($job);

    expect(fn () => recordFeedback()->handle($interview, $outsider, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
    ]))->toThrow(AuthorizationException::class);

    expect(InterviewFeedback::query()->count())->toBe(0);
});

test('a result the domain does not define is refused', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $interview = heldInterview(applicationForJob($job));

    [$criterion] = jobCriteriaOf($job);

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => 'strong_hire', 'evidence_note' => null],
    ]))->toThrow(ValidationException::class);

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
    ]))->toThrow(ValidationException::class);

    expect(fn () => recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => 'first-one', 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => null],
    ]))->toThrow(ValidationException::class);

    expect(InterviewFeedback::query()->count())->toBe(0);
});

test('recording feedback moves nobody and re-evaluates nothing', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $application = applicationForJob($job);
    $interview = heldInterview($application);

    [$criterion] = jobCriteriaOf($job);

    // A *populated* evaluation, not an empty one. Comparing null to null after
    // the action would prove only that nothing was created; the claim AC11 and
    // AC12 actually make is that an existing fit score, evidence coverage,
    // criterion result and interview brief all survive untouched.
    $application->update([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $job->criteria_generation,
        'analysis_score' => 72.5,
        'analysis_coverage' => 60,
        'analyzed_at' => now(),
    ]);

    $score = $application->criterionScores()->create([
        'company_id' => $application->company_id,
        'criterion' => $criterion->criterion,
        'weight' => $criterion->weight,
        'score' => null,
        'reason' => 'The application does not describe team leadership.',
        'evidence' => null,
        'confidence' => AnalysisConfidence::Low,
    ]);

    $briefItem = $application->interviewBriefItems()->create([
        'company_id' => $application->company_id,
        'application_criterion_score_id' => $score->getKey(),
        'criterion' => $criterion->criterion,
        'priority' => 'high',
        'reason' => 'Weighted heavily and unsupported by the application.',
        'question' => 'Tell me about a team you led.',
        'sort_order' => 1,
    ]);

    $before = $application->fresh();
    $scoreBefore = $score->fresh()->only(['criterion', 'weight', 'score', 'reason', 'confidence']);
    $briefBefore = $briefItem->fresh()->only(['criterion', 'priority', 'reason', 'question']);

    // Faked after the fixtures exist, so creating them is not swallowed.
    Queue::fake();
    Event::fake();

    recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'Clear evidence.'],
    ], 'Would hire.');

    $after = $application->fresh();

    expect($after->status_id)->toBe($before->status_id)
        ->and((string) $after->status_entered_at)->toBe((string) $before->status_entered_at)
        ->and($after->analysis_status)->toBe($before->analysis_status)
        // Load-bearing precisely because these are non-null: a recalculation
        // would have to change one of them.
        ->and($after->analysis_score)->toBe($before->analysis_score)
        ->and($after->analysis_score)->not->toBeNull()
        ->and($after->analysis_coverage)->toBe($before->analysis_coverage)
        ->and($after->analysis_coverage)->not->toBeNull()
        ->and($after->analysis_criteria_generation)->toBe($before->analysis_criteria_generation)
        // The AI's own per-criterion result and the original interview brief are
        // still exactly what the evaluation wrote, not rewritten by the human
        // observation about the same criterion.
        ->and($score->fresh()->only(['criterion', 'weight', 'score', 'reason', 'confidence']))->toBe($scoreBefore)
        ->and($briefItem->fresh()->only(['criterion', 'priority', 'reason', 'question']))->toBe($briefBefore)
        ->and(DB::table('application_criterion_scores')->count())->toBe(1)
        ->and(DB::table('application_interview_brief_items')->count())->toBe(1);

    Queue::assertNothingPushed();
});

test('each interview on an application keeps its own feedback record', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $application = applicationForJob($job);

    $screening = heldInterview($application);
    $technical = heldInterview($application);

    [$criterion] = jobCriteriaOf($job);

    $screeningFeedback = recordFeedback()->handle($screening, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::PartiallyConfirmed, 'evidence_note' => 'Promising on paper.'],
    ]);

    $technicalFeedback = recordFeedback()->handle($technical, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'Proved it in the pairing session.'],
    ]);

    expect($technicalFeedback->id)->not->toBe($screeningFeedback->id)
        ->and(InterviewFeedback::query()->where('application_id', $application->id)->count())->toBe(2)
        ->and($screeningFeedback->fresh()->criteria()->firstOrFail()->result)->toBe(InterviewFeedbackResult::PartiallyConfirmed)
        ->and($technicalFeedback->fresh()->criteria()->firstOrFail()->result)->toBe(InterviewFeedbackResult::Confirmed);
});

test('two interviewers on one interview keep independent records', function (): void {
    $company = feedbackCompany();
    $recruiter = feedbackInterviewer($company);
    $engineer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $interview = heldInterview(applicationForJob($job));

    [$criterion] = jobCriteriaOf($job);

    $recruiterFeedback = recordFeedback()->handle($interview, $recruiter, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'Convincing story.'],
    ]);

    $engineerFeedback = recordFeedback()->handle($interview, $engineer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::NotConfirmed, 'evidence_note' => 'Could not go deep on it.'],
    ]);

    expect($engineerFeedback->id)->not->toBe($recruiterFeedback->id)
        ->and(InterviewFeedback::query()->where('interview_id', $interview->id)->count())->toBe(2)
        ->and($recruiterFeedback->fresh()->criteria()->firstOrFail()->result)->toBe(InterviewFeedbackResult::Confirmed)
        ->and($engineerFeedback->fresh()->criteria()->firstOrFail()->result)->toBe(InterviewFeedbackResult::NotConfirmed);
});

test('an interviewer correcting their own feedback updates their record and leaves the other untouched', function (): void {
    $company = feedbackCompany();
    $recruiter = feedbackInterviewer($company);
    $engineer = feedbackInterviewer($company);
    $job = feedbackJob($company, [
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
        ['criterion' => 'Led a team of 5+ engineers', 'weight' => 6],
    ]);
    $interview = heldInterview(applicationForJob($job));

    [$first, $second] = jobCriteriaOf($job);

    $engineerFeedback = recordFeedback()->handle($interview, $engineer, [
        ['job_criterion_id' => $first->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'Deep Laravel knowledge.'],
    ]);

    $original = recordFeedback()->handle($interview, $recruiter, [
        ['job_criterion_id' => $first->id, 'result' => InterviewFeedbackResult::NotAssessed, 'evidence_note' => null],
        ['job_criterion_id' => $second->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'Ran a team of six.'],
    ], 'First impression.');

    $corrected = recordFeedback()->handle($interview, $recruiter, [
        ['job_criterion_id' => $second->id, 'result' => InterviewFeedbackResult::PartiallyConfirmed, 'evidence_note' => 'Team of six, but only for one quarter.'],
    ], 'After re-reading my notes.');

    expect($corrected->id)->toBe($original->id)
        ->and(InterviewFeedback::query()->where('interview_id', $interview->id)->where('submitted_by_id', $recruiter->id)->count())->toBe(1)
        ->and($corrected->general_note)->toBe('After re-reading my notes.');

    $criteria = $corrected->fresh()->criteria()->get();

    expect($criteria)->toHaveCount(1)
        ->and($criteria->first()->job_criterion_id)->toBe($second->id)
        ->and($criteria->first()->result)->toBe(InterviewFeedbackResult::PartiallyConfirmed);

    $engineerCriteria = $engineerFeedback->fresh()->criteria()->get();

    expect($engineerCriteria)->toHaveCount(1)
        ->and($engineerCriteria->first()->result)->toBe(InterviewFeedbackResult::Confirmed)
        ->and($engineerCriteria->first()->evidence_note)->toBe('Deep Laravel knowledge.');
});

test('a note that is only whitespace is stored as no note at all', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);
    $interview = heldInterview(applicationForJob($job));

    [$criterion] = jobCriteriaOf($job);

    $feedback = recordFeedback()->handle($interview, $interviewer, [
        ['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => "   \n\t "],
    ], "  \n ");

    expect($feedback->general_note)->toBeNull()
        ->and($feedback->criteria()->firstOrFail()->evidence_note)->toBeNull();
});

test('a referred application records feedback exactly like a direct one', function (): void {
    $company = feedbackCompany();
    $interviewer = feedbackInterviewer($company);
    $job = feedbackJob($company);

    [$criterion] = jobCriteriaOf($job);

    $referral = Referral::factory()->create([
        'company_id' => $company->id,
        'job_id' => $job->id,
    ]);

    $referred = Application::factory()->create([
        'company_id' => $company->id,
        'job_id' => $job->id,
        'referral_id' => $referral->id,
    ]);

    $direct = applicationForJob($job);

    $submission = fn (Application $application) => recordFeedback()->handle(
        heldInterview($application),
        $interviewer,
        [['job_criterion_id' => $criterion->id, 'result' => InterviewFeedbackResult::PartiallyConfirmed, 'evidence_note' => 'Half the depth we need.']],
        'Same reading either way.',
    );

    $referredFeedback = $submission($referred);
    $directFeedback = $submission($direct);

    $comparable = fn (InterviewFeedback $feedback): array => [
        'criteria_generation' => $feedback->criteria_generation,
        'general_note' => $feedback->general_note,
        'criteria' => $feedback->fresh()->criteria()->get()
            ->map(fn (InterviewFeedbackCriterion $row): array => [
                'job_criterion_id' => $row->job_criterion_id,
                'criterion' => $row->criterion,
                'weight' => $row->weight,
                'result' => $row->result->value,
                'evidence_note' => $row->evidence_note,
            ])->all(),
    ];

    expect($comparable($referredFeedback))->toBe($comparable($directFeedback))
        ->and($referredFeedback->application->referral_id)->toBe($referral->id)
        ->and($directFeedback->application->referral_id)->toBeNull();
});
