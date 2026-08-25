<?php

use App\Actions\RecordInterviewFeedback;
use App\Enums\AnalysisConfidence;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\InterviewFeedbackResult;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Company;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * These render the real Filament page for the same reason as
 * EvaluationSurfaceRenderTest: a surface that records the right rows but
 * throws (or silently overwrites) while rendering has not shipped anything.
 *
 * @return array{0: Company, 1: Job, 2: Application, 3: JobCriterion}
 */
function interviewFeedbackFixture(): array
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withConfirmedCriteria([
        ['criterion' => 'Team leadership', 'weight' => 9],
    ])->create(['company_id' => $company->getKey()]);

    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
    ]);

    actAsCompany($company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($company, isQuiet: true);
    Filament::bootCurrentPanel();

    $criterion = $job->jobCriteria()->first();

    return [$company, $job, $application, $criterion];
}

/** The signed-in workspace member — the human every attribution needs. */
function currentInterviewer(): User
{
    $user = auth()->user();

    expect($user)->toBeInstanceOf(User::class);

    return $user;
}

/**
 * Mounts `recordInterviewFeedback`, mutates the seeded (UUID-keyed) repeater
 * row for the given criterion in place, and submits it — mirroring what the
 * real form sends, since replacing the repeater state with a plain list drops
 * the row keys Filament relies on.
 */
function submitInterviewFeedback(
    Application $application,
    Interview $interview,
    JobCriterion $criterion,
    InterviewFeedbackResult $result,
    ?string $evidenceNote,
    ?string $generalNote = null,
): Testable {
    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->mountAction('recordInterviewFeedback', ['interview' => $interview->getKey()]);

    $data = $component->get('mountedActions')[0]['data'];
    $rowKey = null;

    foreach ($data['criteria'] as $key => $row) {
        if ((int) $row['job_criterion_id'] === $criterion->getKey()) {
            $rowKey = $key;
            break;
        }
    }

    expect($rowKey)->not->toBeNull();

    $data['criteria'][$rowKey]['result'] = $result->value;
    $data['criteria'][$rowKey]['evidence_note'] = $evidenceNote;
    $data['general_note'] = $generalNote;

    return $component->setActionData($data)->callMountedAction();
}

it('offers the record-feedback affordance only for held interviews', function (): void {
    [, , $application] = interviewFeedbackFixture();

    $held = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);
    $future = Interview::factory()->for($application)->create(['company_id' => $application->company_id]);
    $cancelled = Interview::factory()->held()->cancelled()->for($application)->create(['company_id' => $application->company_id]);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    $response->assertSee("mountAction('recordInterviewFeedback', { interview: {$held->getKey()} })", false);
    $response->assertDontSee("mountAction('recordInterviewFeedback', { interview: {$future->getKey()} })", false);
    $response->assertDontSee("mountAction('recordInterviewFeedback', { interview: {$cancelled->getKey()} })", false);
});

it('persists attributed feedback without touching the hiring workflow', function (): void {
    [, , $application, $criterion] = interviewFeedbackFixture();
    $interview = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);
    $author = currentInterviewer();
    $originalStatusId = $application->status_id;
    $originalAnalysisStatus = $application->analysis_status;

    submitInterviewFeedback(
        $application,
        $interview,
        $criterion,
        InterviewFeedbackResult::Confirmed,
        'Described managing a team of seven engineers.',
        'Strong communicator.',
    );

    $feedback = InterviewFeedback::query()
        ->where('interview_id', $interview->getKey())
        ->where('submitted_by_id', $author->getKey())
        ->first();

    expect($feedback)->not->toBeNull();
    expect($feedback->general_note)->toBe('Strong communicator.');

    $criterionResult = $feedback->criteria()->where('job_criterion_id', $criterion->getKey())->first();
    expect($criterionResult)->not->toBeNull();
    expect($criterionResult->result)->toBe(InterviewFeedbackResult::Confirmed);
    expect($criterionResult->evidence_note)->toBe('Described managing a team of seven engineers.');

    $application->refresh();
    expect($application->status_id)->toBe($originalStatusId);
    expect($application->analysis_status)->toBe($originalAnalysisStatus);
});

it('refuses feedback for a cancelled interview without a server error', function (): void {
    [, , $application, $criterion] = interviewFeedbackFixture();
    $interview = Interview::factory()->held()->cancelled()->for($application)->create(['company_id' => $application->company_id]);

    submitInterviewFeedback(
        $application,
        $interview,
        $criterion,
        InterviewFeedbackResult::Confirmed,
        'Would not apply.',
    )->assertSuccessful();

    expect(InterviewFeedback::query()->where('interview_id', $interview->getKey())->exists())->toBeFalse();
});

it('renders both the application evidence and the human evidence layers for the same criterion', function (): void {
    [, $job, $application, $criterion] = interviewFeedbackFixture();

    $application->update([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $job->criteria_generation,
    ]);

    $application->criterionScores()->create([
        'company_id' => $application->company_id,
        'criterion' => $criterion->criterion,
        'weight' => $criterion->weight,
        'score' => null,
        'reason' => 'The application does not describe team leadership.',
        'evidence' => null,
        'confidence' => AnalysisConfidence::Low,
    ]);

    $interview = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);
    app(RecordInterviewFeedback::class)->handle($interview, currentInterviewer(), [
        [
            'job_criterion_id' => $criterion->getKey(),
            'result' => InterviewFeedbackResult::Confirmed,
            'evidence_note' => 'Described managing a team of seven engineers.',
        ],
    ]);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    $response->assertSee(__('applications.admin.interviews.evidence.application_layer'));
    $response->assertSee(__('applications.admin.interviews.evidence.human_evidence_label'));
    $response->assertSee('Described managing a team of seven engineers.');

    // AC14: the human confirmation does not rewrite history. The application
    // layer must still say the submitted application could not support a
    // judgement, beside the interview evidence that now can.
    $response->assertSee(__('applications.admin.ai.criteria.not_assessed'));
    $response->assertSee('The application does not describe team leadership.');
});

it('shows interview evidence for a criterion the evaluation never measured, without inventing application evidence', function (): void {
    // AC14's other half: the criterion has no `ApplicationCriterionScore` at
    // all, so the application layer has nothing to say. Interview evidence must
    // still render, and the application layer must state its own absence rather
    // than being backfilled from what the interviewer observed.
    [, $job, $application, $criterion] = interviewFeedbackFixture();

    $application->update([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $job->criteria_generation,
    ]);

    $interview = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);
    app(RecordInterviewFeedback::class)->handle($interview, currentInterviewer(), [
        [
            'job_criterion_id' => $criterion->getKey(),
            'result' => InterviewFeedbackResult::Confirmed,
            'evidence_note' => 'Ran the on-call rotation for two years.',
        ],
    ]);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    $response->assertSee(__('applications.admin.interviews.evidence.application_absent'));
    $response->assertSee('Ran the on-call rotation for two years.');
});

it('keeps each interview attributable when one application has several', function (): void {
    // AC17 at the surface: two interviews, each with its own recorded evidence,
    // both traceable to the interview that produced them.
    [, , $application, $criterion] = interviewFeedbackFixture();
    $interviewer = currentInterviewer();

    $screening = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);
    $technical = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);

    app(RecordInterviewFeedback::class)->handle($screening, $interviewer, [
        [
            'job_criterion_id' => $criterion->getKey(),
            'result' => InterviewFeedbackResult::PartiallyConfirmed,
            'evidence_note' => 'Led a squad of three, no hiring involvement.',
        ],
    ]);

    app(RecordInterviewFeedback::class)->handle($technical, $interviewer, [
        [
            'job_criterion_id' => $criterion->getKey(),
            'result' => InterviewFeedbackResult::Confirmed,
            'evidence_note' => 'Walked through running performance reviews for seven reports.',
        ],
    ]);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    // Both observations stand: the later one does not replace the earlier, and
    // neither is collapsed into a single result for the criterion.
    $response->assertSee('Led a squad of three, no hiring involvement.');
    $response->assertSee('Walked through running performance reviews for seven reports.');
    $response->assertSee(__('applications.admin.interviews.feedback.results.partially_confirmed'));
    $response->assertSee(__('applications.admin.interviews.feedback.results.confirmed'));

    expect(InterviewFeedback::query()->where('application_id', $application->getKey())->count())->toBe(2)
        ->and(InterviewFeedback::query()->where('interview_id', $screening->getKey())->count())->toBe(1)
        ->and(InterviewFeedback::query()->where('interview_id', $technical->getKey())->count())->toBe(1);
});

it('renders a not-assessed result under the not-covered heading, never under human evidence', function (): void {
    [, , $application, $criterion] = interviewFeedbackFixture();
    $interview = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);

    app(RecordInterviewFeedback::class)->handle($interview, currentInterviewer(), [
        [
            'job_criterion_id' => $criterion->getKey(),
            'result' => InterviewFeedbackResult::NotAssessed,
            'evidence_note' => 'Ran out of time before reaching this topic.',
        ],
    ]);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    $response->assertSee(__('applications.admin.interviews.evidence.not_assessed_note_label'));
    $response->assertSee('Ran out of time before reaching this topic.');
});

it('keeps two interviewers coexisting on the same interview without overwriting each other', function (): void {
    [$company, , $application, $criterion] = interviewFeedbackFixture();
    $interview = Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);

    $firstAuthor = currentInterviewer();

    $secondAuthor = User::factory()->create();
    $secondAuthor->companies()->attach($company);

    app(RecordInterviewFeedback::class)->handle($interview, $firstAuthor, [
        ['job_criterion_id' => $criterion->getKey(), 'result' => InterviewFeedbackResult::Confirmed, 'evidence_note' => 'First interviewer observation.'],
    ]);

    app(RecordInterviewFeedback::class)->handle($interview, $secondAuthor, [
        ['job_criterion_id' => $criterion->getKey(), 'result' => InterviewFeedbackResult::PartiallyConfirmed, 'evidence_note' => 'Second interviewer observation.'],
    ]);

    expect(InterviewFeedback::query()->where('interview_id', $interview->getKey())->count())->toBe(2);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    $response->assertSee($firstAuthor->name);
    $response->assertSee($secondAuthor->name);
});

it('renders the interview brief under its own pre-interview heading, distinct from interview evidence', function (): void {
    [, , $application, $criterion] = interviewFeedbackFixture();

    $score = $application->criterionScores()->create([
        'company_id' => $application->company_id,
        'criterion' => $criterion->criterion,
        'weight' => $criterion->weight,
        'score' => 6,
        'reason' => 'The application describes leading a small team.',
        'evidence' => null,
        'confidence' => AnalysisConfidence::Medium,
    ]);

    $application->interviewBriefItems()->create([
        'company_id' => $application->company_id,
        'application_criterion_score_id' => $score->getKey(),
        'criterion' => $criterion->criterion,
        'priority' => 'high',
        'reason' => 'Weight is high and the application evidence is thin.',
        'question' => 'Walk me through the last time you led a team through a conflict.',
        'sort_order' => 1,
    ]);

    Interview::factory()->held()->for($application)->create(['company_id' => $application->company_id]);

    $response = Livewire::test(ViewApplication::class, ['record' => $application->getKey()]);

    // Both sections stand on the tab under their own framing: the brief is
    // preparation built before the interview, the evidence section is what a
    // human observed after it. `evidence.heading` is asserted rather than
    // `evidence.card_heading` because the latter only appears once a submission
    // exists, and this case deliberately has none.
    $response->assertSee(__('applications.admin.interviews.brief.heading'));
    $response->assertSee(__('applications.admin.interviews.evidence.heading'));

    // AC19: the brief says in its own copy that it is not interview feedback,
    // so it cannot be read as a human observation just because it now shares a
    // tab with one.
    $response->assertSee(__('applications.admin.interviews.brief.description'));
});
