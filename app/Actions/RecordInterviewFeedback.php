<?php

namespace App\Actions;

use App\Enums\InterviewFeedbackResult;
use App\Enums\InterviewStatus;
use App\Exceptions\InterviewFeedbackException;
use App\Models\Application;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * A human records what an interview established about the job's criteria.
 *
 * This is the counterpart of {@see ReplaceApplicationFitAnalysis}: that action
 * persists what the *application* suggested, this one persists what a *person*
 * observed. They are stored apart on purpose, and this action never crosses
 * over — human evidence that quietly rewrote an AI evaluation would erase the
 * distinction the product exists to keep.
 *
 * What it guarantees:
 *
 * - **The evidence is attributable.** Author, interview, application, job and
 *   submission time are all written; the author must be a member of the
 *   interview's workspace. Membership only — no owner, recruiter or
 *   hiring-manager distinction is implied or introduced.
 * - **The interview actually happened.** A cancelled interview never took place,
 *   and an interview that has not ended yet cannot have established anything.
 *   Feedback is evidence, not a pre-interview judgement, so neither is allowed
 *   in. This reads the schedule ({@see Interview::canReceiveFeedback()}) rather
 *   than adding a "completed" interview status.
 * - **Criterion identity is an ID belonging to the interviewed job.** Each
 *   result is resolved against a {@see JobCriterion} of the application's own
 *   job *and* company, and the authoritative `criterion` text and `weight` are
 *   read from that record — never from text supplied by the caller. A criterion
 *   from another job, another workspace, or one that does not exist fails the
 *   whole submission rather than being skipped: evidence from one hiring process
 *   must never silently become evidence for another.
 * - **What the interviewer evaluated stays readable later.** The criterion
 *   snapshot plus the job's `criteria_generation` at submission time mean a
 *   later criteria edit is visibly a later edit, not a retroactive rewrite.
 *
 * What it deliberately does **not** do — these are acceptance criteria, not
 * omissions:
 *
 * - it does not touch `applications.status_id` or `status_entered_at`, and never
 *   calls {@see MoveApplicationToStatus}: advancing, rejecting, hiring or
 *   closing a candidate stays an explicit human workflow action;
 * - it does not touch `applications.analysis_*`, `application_criterion_scores`
 *   or `application_interview_brief_items`, so AI fit, evidence coverage,
 *   criterion confidence and the original interview brief are unchanged;
 * - it recalculates nothing. There is no aggregate human score, because a number
 *   derived from these four results would be an automatic re-evaluation wearing
 *   an average's clothes, and `Not assessed` would become a zero;
 * - it dispatches no job, no AI request and no event.
 *
 * Feedback is per interviewer, per interview. Two interviewers on one interview
 * keep independent records; the same interviewer correcting their own feedback
 * updates the record they already submitted.
 */
class RecordInterviewFeedback
{
    /**
     * @param  User  $author  The human who conducted or attended the interview.
     *                        Recorded as `submitted_by_id`, and required to be a
     *                        member of the interview's company.
     * @param  array<int, mixed>  $criterionResults  One entry per assessed
     *                                               criterion:
     *                                               `{job_criterion_id: int,
     *                                               result: InterviewFeedbackResult|string,
     *                                               evidence_note?: ?string}`.
     * @param  string|null  $generalNote  Optional observation that does not
     *                                    belong cleanly to one criterion.
     *
     * @throws AuthorizationException When the author does not belong to the
     *                                interview's workspace.
     * @throws InterviewFeedbackException When the interview cannot receive
     *                                    feedback, or a criterion does not
     *                                    belong to the interviewed job.
     */
    public function handle(
        Interview $interview,
        User $author,
        array $criterionResults,
        ?string $generalNote = null,
    ): InterviewFeedback {
        return DB::transaction(function () use ($interview, $author, $criterionResults, $generalNote): InterviewFeedback {
            $lockedInterview = Interview::query()
                ->whereKey($interview->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertBelongsToWorkspace($lockedInterview, $author);
            $this->assertInterviewHasTakenPlace($lockedInterview);

            $application = Application::query()
                ->whereKey($lockedInterview->application_id)
                ->firstOrFail();

            $job = Job::query()->whereKey($application->job_id)->firstOrFail();

            $results = $this->normaliseResults($criterionResults);
            $criteria = $this->authoritativeCriteria($results, $application);

            $feedback = $this->upsertFeedback($lockedInterview, $application, $job, $author, $generalNote);

            // A correction replaces the previous answers wholesale, so a
            // criterion the interviewer removed from their submission does not
            // linger as evidence they no longer stand behind.
            $feedback->criteria()->delete();
            $feedback->criteria()->createMany(array_map(
                fn (array $result): array => [
                    'company_id' => $feedback->company_id,
                    'job_criterion_id' => $result['job_criterion_id'],
                    // Snapshot of the criterion, resolved by ID.
                    'criterion' => $criteria[$result['job_criterion_id']]->criterion,
                    'weight' => $criteria[$result['job_criterion_id']]->weight,
                    'result' => $result['result'],
                    'evidence_note' => $result['evidence_note'],
                ],
                $results,
            ));

            return $feedback;
        });
    }

    /**
     * Membership in the interview's company, read from the current tenancy
     * model. It deliberately asks nothing about what the user may do beyond
     * that: feedback needs a named human from this workspace, not a role.
     *
     * @throws AuthorizationException
     */
    private function assertBelongsToWorkspace(Interview $interview, User $author): void
    {
        if (! $author->exists || $author->getKey() === null || ! User::query()
            ->whereKey($author->getKey())
            ->whereHas('companies', fn ($companies) => $companies->whereKey($interview->company_id))
            ->exists()) {
            throw new AuthorizationException('Only a member of this workspace may record feedback for this interview.');
        }
    }

    /**
     * Feedback describes an interview that happened. The two ways it cannot have
     * happened are reported separately because they mean different things to the
     * recruiter: one was called off, the other simply has not run yet.
     *
     * @throws InterviewFeedbackException
     */
    private function assertInterviewHasTakenPlace(Interview $interview): void
    {
        if ($interview->status === InterviewStatus::Cancelled) {
            throw InterviewFeedbackException::interviewCancelled();
        }

        if (! $interview->canReceiveFeedback()) {
            throw InterviewFeedbackException::interviewNotHeldYet();
        }
    }

    /**
     * @param  array<int, mixed>  $criterionResults
     * @return list<array{job_criterion_id: int, result: string, evidence_note: string|null}>
     *
     * @throws InterviewFeedbackException
     */
    private function normaliseResults(array $criterionResults): array
    {
        if ($criterionResults === []) {
            throw InterviewFeedbackException::noCriteriaSubmitted();
        }

        /** @var array{results: array<int, array<string, mixed>>} $validated */
        $validated = Validator::make(
            ['results' => array_values(array_map($this->unwrapResultEnum(...), $criterionResults))],
            [
                'results' => ['required', 'array', 'min:1'],
                'results.*' => ['required', 'array:job_criterion_id,result,evidence_note'],
                'results.*.job_criterion_id' => ['required', 'integer'],
                'results.*.result' => ['required', 'string', Rule::in(InterviewFeedbackResult::values())],
                'results.*.evidence_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ],
        )->validate();

        $results = array_values(array_map(fn (array $result): array => [
            'job_criterion_id' => (int) $result['job_criterion_id'],
            'result' => (string) $result['result'],
            // Whitespace-only writing is an empty note, not evidence.
            'evidence_note' => $this->cleanNote($result['evidence_note'] ?? null),
        ], $validated['results']));

        $criterionIds = array_map(fn (array $result): int => $result['job_criterion_id'], $results);

        if (count($criterionIds) !== count(array_unique($criterionIds))) {
            // Last-one-wins would silently discard one of two contradictory
            // answers the same interviewer gave for the same criterion.
            throw InterviewFeedbackException::duplicateCriterion();
        }

        return $results;
    }

    /**
     * Accepts the enum the domain speaks in, as well as the raw string a form
     * submits, without letting the validation rules below drift apart.
     */
    private function unwrapResultEnum(mixed $result): mixed
    {
        if (is_array($result) && ($result['result'] ?? null) instanceof InterviewFeedbackResult) {
            $result['result'] = $result['result']->value;
        }

        return $result;
    }

    /**
     * The submitted criteria, keyed by ID, restricted to the interviewed job and
     * to the application's own company. Anything that does not resolve — another
     * job, another workspace, or a criterion that no longer exists — fails the
     * whole submission: a partially recorded interview would look complete while
     * quietly missing an assessment.
     *
     * @param  list<array{job_criterion_id: int, result: string, evidence_note: string|null}>  $results
     * @return array<int, JobCriterion>
     *
     * @throws InterviewFeedbackException
     */
    private function authoritativeCriteria(array $results, Application $application): array
    {
        $criterionIds = array_map(fn (array $result): int => $result['job_criterion_id'], $results);

        $criteria = [];

        foreach (JobCriterion::query()
            ->where('job_id', $application->job_id)
            ->where('company_id', $application->company_id)
            ->whereKey($criterionIds)
            ->get() as $criterion) {
            $criteria[(int) $criterion->getKey()] = $criterion;
        }

        if (count($criteria) !== count($criterionIds)) {
            throw InterviewFeedbackException::criterionOutsideInterviewedJob();
        }

        return $criteria;
    }

    /**
     * One record per (interview, author). Re-submitting updates the record the
     * author already has instead of accumulating drafts beside it.
     *
     * `submitted_at` is re-stamped on every submission, so "submitted at" always
     * describes the content currently displayed rather than an earlier version
     * of it; `created_at` preserves when the interviewer first recorded
     * feedback, so nothing about the original submission is lost.
     *
     * `company_id` is stamped explicitly from the interview rather than left to
     * Filament's tenancy stamping, so the action behaves identically when it is
     * called outside a panel request.
     */
    private function upsertFeedback(
        Interview $interview,
        Application $application,
        Job $job,
        User $author,
        ?string $generalNote,
    ): InterviewFeedback {
        $feedback = InterviewFeedback::query()
            ->where('interview_id', $interview->getKey())
            ->where('submitted_by_id', $author->getKey())
            ->lockForUpdate()
            ->first() ?? new InterviewFeedback;

        $feedback->forceFill([
            'company_id' => $interview->company_id,
            'interview_id' => $interview->getKey(),
            'application_id' => $application->getKey(),
            'job_id' => $job->getKey(),
            'submitted_by_id' => $author->getKey(),
            'submitted_at' => CarbonImmutable::now(),
            // The criteria revision this interview was assessed against. Later
            // edits to the job advance it, which is what keeps historical
            // feedback readable as history instead of as a current assessment.
            'criteria_generation' => $job->criteria_generation,
            'general_note' => $this->cleanNote($generalNote),
        ])->save();

        return $feedback;
    }

    /**
     * An empty or whitespace-only note is the absence of a note, and must not be
     * stored as an empty string that later renders as a blank piece of
     * "evidence".
     */
    private function cleanNote(mixed $note): ?string
    {
        $trimmed = is_string($note) ? trim($note) : '';

        return $trimmed === '' ? null : $trimmed;
    }
}
