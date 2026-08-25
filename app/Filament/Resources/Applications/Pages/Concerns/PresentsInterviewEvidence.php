<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Enums\InterviewFeedbackResult;
use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\InterviewFeedbackCriterion;
use App\Models\JobCriterion;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Reading the human evidence an interview produced.
 *
 * The counterpart of {@see ManagesInterviewFeedback}, which writes it. Nothing
 * here touches `applications.analysis_*` or `application_criterion_scores`: the
 * application evaluation is shown beside the human observations exactly as it
 * was recorded, because recording feedback never rewrites it.
 *
 * Three rules shape everything below and are easy to break by accident:
 *
 * - the two layers stay labelled and separate. Interview evidence is written by
 *   a named person and must never read as AI output, and the application
 *   evaluation must never read as something an interviewer confirmed;
 * - no result is ever aggregated into a number. Two interviewers may disagree,
 *   and both observations stand — nothing here averages, votes, ranks, or picks
 *   a winner, and criteria are ordered by their own weight, never by how
 *   favourable the results are;
 * - `Not assessed` is unresolved, not negative. It is never coloured as a
 *   finding and its note never renders under a heading that reads as evidence
 *   about the candidate.
 *
 * Composition requirements, which are not all satisfied by the host page:
 * `getApplication()`, `needsValidation()` and `importanceForWeight()` come from
 * the page itself, but `criterionKey()` comes from the sibling trait
 * {@see ManagesInterviewFeedback}. This trait therefore cannot be composed
 * alone — a page that takes the read side without the write side fatals at
 * runtime rather than failing analysis.
 */
trait PresentsInterviewEvidence
{
    /**
     * The four results as they look everywhere they are rendered — the form, the
     * interview cards and the aggregated section all read from here, so a result
     * cannot mean one thing while being recorded and another while being
     * reviewed.
     *
     * Only `Not confirmed` is danger-coloured. Not having asked about something
     * is grey: it is missing information, never a finding against the candidate.
     *
     * @return array<string, string>
     */
    private function interviewFeedbackResultColors(): array
    {
        return [
            InterviewFeedbackResult::Confirmed->value => 'success',
            InterviewFeedbackResult::PartiallyConfirmed->value => 'warning',
            InterviewFeedbackResult::NotConfirmed->value => 'danger',
            InterviewFeedbackResult::NotAssessed->value => 'gray',
        ];
    }

    /** @return array<string, Heroicon> */
    private function interviewFeedbackResultIcons(): array
    {
        return [
            InterviewFeedbackResult::Confirmed->value => Heroicon::OutlinedCheckCircle,
            InterviewFeedbackResult::PartiallyConfirmed->value => Heroicon::OutlinedMinusCircle,
            InterviewFeedbackResult::NotConfirmed->value => Heroicon::OutlinedXCircle,
            InterviewFeedbackResult::NotAssessed->value => Heroicon::OutlinedQuestionMarkCircle,
        ];
    }

    /**
     * Every submission for this application, oldest first.
     *
     * Read through {@see Application::interviewFeedback()} — a relation on an
     * already tenant-scoped application — so no direct `InterviewFeedback` query
     * needs a company filter of its own. The relation itself is ordered newest
     * first for other callers; interview evidence reads chronologically, so it
     * is re-sorted here in memory rather than issuing a second query.
     *
     * @return Collection<int, InterviewFeedback>
     */
    private function submittedInterviewFeedback(Application $application): Collection
    {
        $application->loadMissing([
            'interviewFeedback.criteria',
            'interviewFeedback.submittedBy',
        ]);

        return $application->interviewFeedback
            ->sortBy([['submitted_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * Submissions grouped by the interview that produced them, so each interview
     * card can render its own without querying per card.
     *
     * @return Collection<int, Collection<int, InterviewFeedback>>
     */
    private function interviewFeedbackByInterview(Application $application): Collection
    {
        return $this->submittedInterviewFeedback($application)
            ->groupBy(fn (InterviewFeedback $feedback): int => (int) $feedback->interview_id);
    }

    /**
     * Every submission in the order the evidence was produced: the interviews in
     * the order they happened, and within one interview the submissions in the
     * order they were written.
     *
     * The interview comes first because that is the event a recruiter reads
     * along; submission time only breaks ties inside it. Both keys are
     * zero-padded so the composite sorts numerically.
     *
     * @param  Collection<int, Interview>  $interviews
     * @return Collection<int, InterviewFeedback>
     */
    private function chronologicalInterviewFeedback(Application $application, Collection $interviews): Collection
    {
        return $this->submittedInterviewFeedback($application)
            ->sortBy(function (InterviewFeedback $feedback) use ($interviews): string {
                $interview = $interviews->get((int) $feedback->interview_id);

                return sprintf(
                    '%012d%012d%012d',
                    $interview instanceof Interview ? $interview->scheduled_at->getTimestamp() : 0,
                    $feedback->submitted_at->getTimestamp(),
                    (int) $feedback->getKey(),
                );
            })
            ->values();
    }

    /**
     * One interview's submissions, as the card renders them.
     *
     * Several submissions stay several attributed blocks. Two interviewers who
     * saw the same candidate differently are both right about what they saw, and
     * merging them would invent a consensus nobody reached.
     *
     * @param  Collection<int, InterviewFeedback>  $submissions
     * @return list<array<string, mixed>>
     */
    private function interviewFeedbackCardData(Interview $interview, Collection $submissions, int $currentCriteriaGeneration): array
    {
        $state = $this->interviewEvidenceState($interview);

        return array_values($submissions
            ->sortBy([['submitted_at', 'asc'], ['id', 'asc']])
            ->map(fn (InterviewFeedback $feedback): array => [
                'author' => $this->interviewFeedbackAuthor($feedback),
                'submitted_at' => $feedback->submitted_at->translatedFormat('M j, Y · H:i'),
                'is_historical' => (int) $feedback->criteria_generation !== $currentCriteriaGeneration,
                'general_note' => $feedback->general_note,
                'criteria' => $this->interviewFeedbackCriteriaData($feedback),
                'interview_state' => $state,
            ])
            ->all());
    }

    /**
     * The criterion results inside one submission, ordered by the criterion's own
     * weight. Never by result: a list that puts confirmations first would read as
     * a verdict the interviewer did not write.
     *
     * @return list<array<string, mixed>>
     */
    private function interviewFeedbackCriteriaData(InterviewFeedback $feedback): array
    {
        return array_values($feedback->criteria
            ->sortBy([['weight', 'desc'], ['criterion', 'asc']])
            ->map(fn (InterviewFeedbackCriterion $criterion): array => [
                'criterion' => $criterion->criterion,
                ...$this->interviewFeedbackResultData($criterion->result),
                'note' => $criterion->evidence_note,
            ])
            ->all());
    }

    /** @return array<string, bool|string> */
    private function interviewFeedbackResultData(InterviewFeedbackResult $result): array
    {
        return [
            'result' => $result->value,
            'result_label' => (string) __("applications.admin.interviews.feedback.results.{$result->value}"),
            'result_color' => $this->interviewFeedbackResultColors()[$result->value],
            // The enum carries the bare heroicon name (`o-check-circle`);
            // Blade resolves icons by their full registered name, as the
            // surrounding views do.
            'result_icon' => 'heroicon-'.$this->interviewFeedbackResultIcons()[$result->value]->value,
            // Drives which heading the note renders under: an assertion is
            // evidence about the candidate, `Not assessed` is the interviewer
            // explaining why the criterion was not covered.
            'is_assertion' => $result->isAssertion(),
            'resolves_uncertainty' => $result->resolvesUncertainty(),
        ];
    }

    /**
     * The aggregated view of interview evidence, grouped by criterion.
     *
     * It answers, per criterion: what the application supported before the
     * interview, what each interviewer observed, who said it, when, and from
     * which interview. It deliberately answers nothing else — there is no human
     * score, no combined score and no recommendation, because the hiring
     * decision is not this section's to make.
     *
     * @return array{shows_application_evidence: bool, unresolved_count: int, criteria: list<array<string, mixed>>}
     */
    private function interviewEvidenceData(Application $application): array
    {
        $application->loadMissing(['interviews', 'job.jobCriteria', 'criterionScores']);

        $interviews = $application->interviews->keyBy(fn (Interview $interview): int => (int) $interview->getKey());
        $currentCriteria = $application->job->jobCriteria->keyBy(fn (JobCriterion $criterion): int => (int) $criterion->getKey());
        $currentGeneration = (int) $application->job->criteria_generation;

        // A stale evaluation is never presented as the answer, so a criterion
        // simply shows no application layer when the evaluation no longer
        // measures this job's criteria. The interview evidence stands on its own
        // regardless: it was never derived from the evaluation.
        $showsApplicationEvidence = $application->hasCurrentEvaluation();

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($this->chronologicalInterviewFeedback($application, $interviews) as $feedback) {
            $interview = $interviews->get((int) $feedback->interview_id);
            $isHistorical = (int) $feedback->criteria_generation !== $currentGeneration;

            foreach ($feedback->criteria as $criterion) {
                $jobCriterion = $criterion->job_criterion_id === null
                    ? null
                    : $currentCriteria->get((int) $criterion->job_criterion_id);

                // Prefer the criterion the feedback still points at; fall back to
                // the text snapshot so a deleted criterion keeps its observations
                // together instead of splitting into one group per submission.
                $key = $jobCriterion instanceof JobCriterion
                    ? 'criterion:'.$jobCriterion->getKey()
                    : 'snapshot:'.$this->criterionKey($criterion->criterion);

                $groups[$key] ??= $this->interviewEvidenceGroup(
                    $application,
                    $jobCriterion,
                    $criterion,
                    $showsApplicationEvidence,
                );

                $entry = [
                    ...$this->interviewFeedbackResultData($criterion->result),
                    'note' => $criterion->evidence_note,
                    'author' => $this->interviewFeedbackAuthor($feedback),
                    'submitted_at' => $feedback->submitted_at->translatedFormat('M j, Y · H:i'),
                    'interview_at' => $interview instanceof Interview
                        ? $interview->scheduled_at->setTimezone($interview->timezone)->translatedFormat('M j, Y · H:i')
                        : null,
                    'interview_state' => $this->interviewEvidenceState($interview),
                    // Only shown when the wording moved on, so a recruiter reading
                    // an old observation sees the criterion it was answering.
                    'recorded_as' => $this->criterionKey($criterion->criterion) === $this->criterionKey((string) $groups[$key]['criterion'])
                        ? null
                        : $criterion->criterion,
                ];

                // Feedback recorded against an earlier revision of the criteria
                // stays readable, keeps its own sub-list, and is never folded in
                // with feedback recorded against the criteria in force today.
                $groups[$key][$isHistorical ? 'historical_entries' : 'entries'][] = $entry;
            }
        }

        $criteria = collect($groups)
            ->map(fn (array $group): array => [
                ...$group,
                'is_unresolved' => $this->interviewEvidenceIsUnresolved($group),
            ])
            // The criteria's own importance decides the order, exactly as the
            // evaluation tab orders them. Ordering by how positive the results
            // are would be a hiring recommendation dressed as a layout.
            ->sortBy([
                ['is_current_criterion', 'desc'],
                ['weight', 'desc'],
                ['criterion', 'asc'],
            ])
            ->values();

        return [
            'shows_application_evidence' => $showsApplicationEvidence,
            // A neutral count of what the interviews left open — never a score,
            // never "N of M resolved", which would read as a grade.
            'unresolved_count' => $criteria->where('is_unresolved', true)->count(),
            'criteria' => array_values($criteria->all()),
        ];
    }

    /**
     * A criterion group, with the pre-interview application state beside it.
     *
     * @return array<string, mixed>
     */
    private function interviewEvidenceGroup(
        Application $application,
        ?JobCriterion $jobCriterion,
        InterviewFeedbackCriterion $criterion,
        bool $showsApplicationEvidence,
    ): array {
        // The criterion as the job words it today when it still exists, and the
        // snapshot taken at submission time when it does not.
        $isCurrent = $jobCriterion instanceof JobCriterion;
        $text = $isCurrent ? $jobCriterion->criterion : $criterion->criterion;
        $weight = $isCurrent ? $jobCriterion->weight : $criterion->weight;

        return [
            'criterion' => $text,
            'weight' => $weight,
            'importance' => $this->importanceForWeight($weight),
            // False means the job no longer lists this criterion. The observation
            // survives; what it was measured against does not.
            'is_current_criterion' => $isCurrent,
            'application' => $showsApplicationEvidence
                ? $this->applicationEvidenceFor($application, $text)
                : null,
            'entries' => [],
            'historical_entries' => [],
        ];
    }

    /**
     * What the submitted application supported for this criterion, as the
     * evaluation recorded it before any interview took place.
     *
     * `application_criterion_scores` snapshots the criterion text and carries no
     * foreign key, so normalised text is the only join available. It is reliable
     * here precisely because the caller gated on
     * {@see Application::hasCurrentEvaluation()}: editing a criterion's wording
     * bumps the job's criteria generation, which makes the evaluation stale and
     * closes that gate.
     *
     * @return array<string, mixed>|null
     */
    private function applicationEvidenceFor(Application $application, string $criterion): ?array
    {
        $score = $application->criterionScores
            ->first(fn (ApplicationCriterionScore $score): bool => $this->criterionKey($score->criterion) === $this->criterionKey($criterion));

        if (! $score instanceof ApplicationCriterionScore) {
            return null;
        }

        return [
            'is_assessed' => $score->isAssessed(),
            'score' => $score->score,
            'confidence' => $score->confidence->value,
            'reason' => $score->reason,
            'needs_validation' => $this->needsValidation($score),
        ];
    }

    /**
     * Whether the interviews left this criterion's uncertainty standing.
     *
     * "Unresolved" means what it meant before the interview: the application
     * could not settle the criterion, and no interviewer settled it either.
     * `Partially confirmed` and `Not assessed` therefore leave it unresolved,
     * while `Not confirmed` resolves it — a negative finding is an answer.
     *
     * A criterion the job no longer lists is excluded: nothing is pending on a
     * criterion this hiring process stopped asking about.
     *
     * @param  array<string, mixed>  $group
     */
    private function interviewEvidenceIsUnresolved(array $group): bool
    {
        if ($group['is_current_criterion'] !== true) {
            return false;
        }

        $application = $group['application'];
        $wasUncertain = ! is_array($application) || $application['needs_validation'] === true;

        if (! $wasUncertain) {
            return false;
        }

        /** @var list<array<string, mixed>> $entries */
        $entries = [...$group['entries'], ...$group['historical_entries']];

        return collect($entries)->doesntContain(fn (array $entry): bool => $entry['resolves_uncertainty'] === true);
    }

    /**
     * Feedback can only be recorded on an interview that took place, but the
     * interview can be cancelled or moved afterwards. The observation stays —
     * it was really made — while the card says so, so it cannot be read as
     * evidence from a commitment that never happened.
     *
     * Whether it still reads as "held" is decided by
     * {@see Interview::canReceiveFeedback()}, the single domain definition of
     * "the interview happened"; the remaining branches only name the reason it
     * no longer holds, so they can never drift from that definition.
     */
    private function interviewEvidenceState(?Interview $interview): string
    {
        return match (true) {
            ! $interview instanceof Interview => 'held',
            $interview->canReceiveFeedback() => 'held',
            $interview->status === InterviewStatus::Cancelled => 'cancelled',
            default => 'rescheduled',
        };
    }

    /**
     * Human feedback is never anonymous. `submitted_by_id` is restricted on
     * delete, so the author is always there; the fallback only exists so
     * attribution can never render as a blank line.
     */
    private function interviewFeedbackAuthor(InterviewFeedback $feedback): string
    {
        $name = $feedback->submittedBy?->name;

        return is_string($name) && $name !== ''
            ? $name
            : (string) __('applications.admin.interviews.evidence.unknown_author');
    }
}
