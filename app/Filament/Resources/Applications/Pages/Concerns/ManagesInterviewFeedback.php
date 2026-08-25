<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Actions\RecordInterviewFeedback;
use App\Enums\InterviewFeedbackResult;
use App\Enums\JobCriteriaProcessingStatus;
use App\Exceptions\InterviewFeedbackException;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\Interview;
use App\Models\InterviewFeedbackCriterion;
use App\Models\JobCriterion;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Recording structured human evidence against an interview's job criteria.
 *
 * Timing does not gate it — before, during or after the slot — because an
 * interviewer taking notes live should not have to wait for it to end. Only
 * cancellation gates it, and {@see PresentsInterviewEvidence} is what marks a
 * note written before the interview finished as exactly that.
 *
 * The counterpart of the AI evaluation surface, and deliberately not part of
 * it: everything here is written by a named person, is attributed to them, and
 * changes nothing about the application's fit, evidence coverage, interview
 * brief or pipeline stage. {@see RecordInterviewFeedback} is the only write
 * path, so this concern never grows a second route around those guarantees.
 */
trait ManagesInterviewFeedback
{
    /**
     * Mounted from an interview's card — upcoming, running or finished, any of
     * them that was not cancelled — and never shown in the page header.
     * Feedback belongs to one specific interview, so the interview has to be
     * named by the button that opens the form.
     *
     * Two details are load-bearing and easy to undo by accident:
     *
     * - the method is `protected`, not `private`. Filament resolves a
     *   `mountAction('recordInterviewFeedback', …)` call by looking for a
     *   `recordInterviewFeedbackAction()` method on the page, and it does that
     *   from a parent class, which cannot reach a private one.
     * - the action is **not** returned from `getHeaderActions()` and is **not**
     *   `hidden()`. Keeping it out of the header is what stops it rendering
     *   there; marking it `hidden()` instead would make
     *   `Filament\Actions\Action::isDisabled()` report it as disabled, and
     *   `mountAction()` silently refuses to mount a disabled action.
     */
    protected function recordInterviewFeedbackAction(): Action
    {
        return Action::make('recordInterviewFeedback')
            ->label(__('applications.admin.actions.record_interview_feedback'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->schema($this->interviewFeedbackSchema())
            ->fillForm(fn (array $arguments): array => $this->interviewFeedbackFormState($this->resolveInterview($arguments)))
            ->modalHeading(__('applications.admin.interviews.feedback.heading'))
            ->modalDescription(__('applications.admin.interviews.feedback.description'))
            ->modalSubmitActionLabel(__('applications.admin.interviews.feedback.confirm'))
            // With no criteria there is nothing to record, so the modal offers
            // no submit at all rather than a button whose only outcome is a red
            // notification the interviewer cannot act on.
            ->modalSubmitAction(fn (Action $action): Action|false => $this->hasInterviewFeedbackCriteria() ? $action : false)
            ->modalWidth('3xl')
            ->action(function (array $data, array $arguments, RecordInterviewFeedback $recordInterviewFeedback): void {
                $application = $this->getApplication();
                $user = $this->getCurrentUser();
                $interview = $this->resolveInterview($arguments);

                Gate::forUser($user)->authorize('update', $application);

                try {
                    $recordInterviewFeedback->handle(
                        $interview,
                        $user,
                        $this->interviewFeedbackResults($data),
                        is_string($data['general_note'] ?? null) ? $data['general_note'] : null,
                    );
                } catch (InterviewFeedbackException|AuthorizationException|ValidationException $exception) {
                    $this->sendInterviewFeedbackFailureNotification($exception);
                }

                $this->refreshApplicationRecord($application);

                Notification::make()
                    ->title(__('applications.admin.interviews.feedback.notifications.recorded'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, Component> */
    private function interviewFeedbackSchema(): array
    {
        return [
            // The legend sits above the rows rather than under them: the four
            // results are not a scale, and "Not assessed" has to be readable as
            // unresolved *before* the interviewer starts answering.
            Section::make(__('applications.admin.interviews.feedback.criteria_label'))
                ->description(fn (): string => $this->interviewFeedbackCriteriaDescription())
                ->schema([
                    $this->interviewFeedbackCriteriaRepeater()
                        ->visible(fn (): bool => $this->hasInterviewFeedbackCriteria()),
                ]),
            Textarea::make('general_note')
                ->label(__('applications.admin.interviews.feedback.general_note'))
                ->helperText(__('applications.admin.interviews.feedback.general_note_helper'))
                ->rows(3)
                ->maxLength(2000)
                // A general note cannot stand on its own: the domain refuses a
                // submission with no criterion result, so offering the field
                // with nothing to attach it to would only bait a refusal.
                ->visible(fn (): bool => $this->hasInterviewFeedbackCriteria()),
        ];
    }

    /**
     * What the interviewer is answering against, said plainly before they start.
     *
     * Three different situations, and conflating any two of them would mislead:
     *
     * - the job has no criteria at all, so there is nothing to record evidence
     *   against and the modal says so instead of rendering an empty list that
     *   only fails on submit;
     * - the criteria exist but are still an unconfirmed AI *suggestion* (see
     *   {@see JobCriteriaProcessingStatus::AwaitingReview}). They are shown,
     *   because feedback is never gated on a later criteria edit, but they are
     *   labelled for what they are — an extraction awaiting human review, not
     *   something the AI established or verified;
     * - the criteria were confirmed by a recruiter, which needs no caveat.
     */
    private function interviewFeedbackCriteriaDescription(): string
    {
        if (! $this->hasInterviewFeedbackCriteria()) {
            return __('applications.admin.interviews.feedback.no_criteria');
        }

        $legend = __('applications.admin.interviews.feedback.criteria_helper');

        return $this->getApplication()->job->hasConfirmedCriteria()
            ? $legend
            : __('applications.admin.interviews.feedback.criteria_unconfirmed').' '.$legend;
    }

    private function hasInterviewFeedbackCriteria(): bool
    {
        $job = $this->getApplication()->job;
        $job->loadMissing('jobCriteria');

        return $job->jobCriteria->isNotEmpty();
    }

    /**
     * The rows are the job's criteria. Nothing may be added, removed or
     * reordered here — the interviewer answers the job's criteria, they do not
     * get to define them.
     */
    private function interviewFeedbackCriteriaRepeater(): Repeater
    {
        return Repeater::make('criteria')
            ->hiddenLabel()
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->defaultItems(0)
            ->itemLabel(fn (array $state): ?string => $this->interviewFeedbackRowLabel($state))
            ->schema([
                Hidden::make('job_criterion_id'),
                // Carried in state only so the row can label itself. The
                // criterion text stored with the feedback is snapshotted by
                // RecordInterviewFeedback from the criterion's own record,
                // never from what this form posts back.
                Hidden::make('criterion'),
                Hidden::make('needs_validation'),
                ToggleButtons::make('result')
                    ->label(__('applications.admin.interviews.feedback.result_label'))
                    ->options($this->interviewFeedbackResultOptions())
                    // The colours and icons come from the presentation concern,
                    // so a result cannot look like one thing while being
                    // recorded and another while being reviewed. "Not assessed"
                    // is grey there and only "Not confirmed" is danger-coloured:
                    // not asking about something must never look like a finding
                    // against the candidate.
                    ->colors($this->interviewFeedbackResultColors())
                    ->icons($this->interviewFeedbackResultIcons())
                    ->tooltips($this->interviewFeedbackResultHints())
                    // A criterion the interviewer never touched is recorded
                    // as unassessed, which is the truth, rather than as a
                    // judgement they never made.
                    ->default(InterviewFeedbackResult::NotAssessed->value)
                    ->inline()
                    ->required(),
                Textarea::make('evidence_note')
                    ->label(__('applications.admin.interviews.feedback.evidence_note'))
                    ->helperText(__('applications.admin.interviews.feedback.evidence_note_helper'))
                    ->rows(2)
                    // Matches RecordInterviewFeedback's own validator, so a
                    // note that is too long is caught in the form instead of
                    // failing the whole submission.
                    ->maxLength(2000),
            ]);
    }

    /**
     * One row per current job criterion, pre-filled with whatever this same
     * author already submitted for this same interview. Re-opening the form
     * therefore shows their own answers, and submitting again corrects them
     * rather than filing a second opinion under their name.
     *
     * @return array{criteria: list<array<string, mixed>>, general_note: string|null}
     */
    private function interviewFeedbackFormState(Interview $interview): array
    {
        $application = $this->getApplication();

        // Read through the interview's own relation, which is already scoped to
        // this application and workspace, so no direct InterviewFeedback query
        // needs a company filter of its own.
        $existing = $interview->feedback()
            ->where('submitted_by_id', $this->getCurrentUser()->getKey())
            ->with('criteria')
            ->first();

        $submitted = $existing === null
            ? collect()
            : $existing->criteria
                ->filter(fn (InterviewFeedbackCriterion $criterion): bool => $criterion->job_criterion_id !== null)
                ->keyBy(fn (InterviewFeedbackCriterion $criterion): int => (int) $criterion->job_criterion_id);

        return [
            'criteria' => array_map(function (array $row) use ($submitted): array {
                $previous = $submitted->get($row['job_criterion_id']);

                return [
                    ...$row,
                    'result' => $previous?->result->value ?? InterviewFeedbackResult::NotAssessed->value,
                    'evidence_note' => $previous?->evidence_note,
                ];
            }, $this->interviewFeedbackCriteriaRows($application)),
            'general_note' => $existing?->general_note,
        ];
    }

    /**
     * The job's current criteria, with the ones the interviewer was asked to
     * validate first.
     *
     * @return list<array{job_criterion_id: int, criterion: string, weight: int, needs_validation: bool}>
     */
    private function interviewFeedbackCriteriaRows(Application $application): array
    {
        $application->loadMissing('job.jobCriteria');
        $uncertainties = $this->preInterviewUncertainties($application);

        return array_values($application->job->jobCriteria
            ->map(fn (JobCriterion $criterion): array => [
                'job_criterion_id' => (int) $criterion->getKey(),
                'criterion' => $criterion->criterion,
                'weight' => $criterion->weight,
                'needs_validation' => $uncertainties->contains($this->criterionKey($criterion->criterion)),
            ])
            ->sortBy([
                ['needs_validation', 'desc'],
                ['weight', 'desc'],
                ['criterion', 'asc'],
            ])
            ->all());
    }

    /**
     * The criteria that were important uncertainties *before* this interview,
     * composed from the two places the recruiter already saw them: the
     * Interview Brief, and the evaluation's own "needs validation" list. No
     * third definition of uncertainty is invented here.
     *
     * Both sources snapshot the criterion text and carry no foreign key to
     * `job_criterion`, so normalised text is the only join available. A
     * criterion whose text changed after the evaluation simply stops matching,
     * which is correct: the marker describes what the recruiter was told before
     * the interview, not what the job says now.
     *
     * @return Collection<int, string>
     */
    private function preInterviewUncertainties(Application $application): Collection
    {
        $application->loadMissing(['criterionScores', 'interviewBriefItems']);

        return $application->interviewBriefItems
            ->map(fn (ApplicationInterviewBriefItem $item): string => $item->criterion)
            ->merge($application->criterionScores
                ->filter(fn (ApplicationCriterionScore $score): bool => $this->needsValidation($score))
                ->map(fn (ApplicationCriterionScore $score): string => $score->criterion))
            ->map(fn (string $criterion): string => $this->criterionKey($criterion))
            ->unique()
            ->values();
    }

    private function criterionKey(string $criterion): string
    {
        return Str::of($criterion)->squish()->lower()->value();
    }

    /**
     * The criterion, plus a quiet marker when it was an uncertainty going into
     * the interview. It is a suffix rather than a badge: the point is that the
     * row is easy to recognise, not that its answer matters more.
     *
     * @param  array<string, mixed>  $state
     */
    private function interviewFeedbackRowLabel(array $state): ?string
    {
        $criterion = $state['criterion'] ?? null;

        if (! is_string($criterion) || $criterion === '') {
            return null;
        }

        return filter_var($state['needs_validation'] ?? false, FILTER_VALIDATE_BOOL)
            ? $criterion.' · '.__('applications.admin.interviews.feedback.needs_validation_marker')
            : $criterion;
    }

    /** @return array<string, string> */
    private function interviewFeedbackResultOptions(): array
    {
        return collect(InterviewFeedbackResult::cases())
            ->mapWithKeys(fn (InterviewFeedbackResult $result): array => [
                $result->value => __("applications.admin.interviews.feedback.results.{$result->value}"),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private function interviewFeedbackResultHints(): array
    {
        return collect(InterviewFeedbackResult::cases())
            ->mapWithKeys(fn (InterviewFeedbackResult $result): array => [
                $result->value => __("applications.admin.interviews.feedback.result_hints.{$result->value}"),
            ])
            ->all();
    }

    /**
     * Every criterion row is submitted, including the untouched ones. The domain
     * replaces an author's answers wholesale, so omitting a row would leave a
     * stale result behind instead of recording "not assessed".
     *
     * @param  array<string, mixed>  $data
     * @return list<array{job_criterion_id: int, result: string, evidence_note: string|null}>
     */
    private function interviewFeedbackResults(array $data): array
    {
        $rows = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];

        return array_values(array_map(fn (array $row): array => [
            'job_criterion_id' => (int) $row['job_criterion_id'],
            'result' => (string) $row['result'],
            'evidence_note' => is_string($row['evidence_note'] ?? null) ? $row['evidence_note'] : null,
        ], $rows));
    }

    /**
     * A refused submission is a message, never an error page. A domain refusal
     * already explains, in the recruiter's own language, why this interview
     * cannot receive feedback; the other two are told generically, because their
     * messages are written for developers.
     */
    private function sendInterviewFeedbackFailureNotification(Throwable $exception): never
    {
        Notification::make()
            ->title(__('applications.admin.interviews.feedback.notifications.failed'))
            ->body(match (true) {
                $exception instanceof InterviewFeedbackException => $exception->getMessage(),
                $exception instanceof AuthorizationException => __('applications.admin.interviews.feedback.notifications.not_allowed'),
                default => __('applications.admin.interviews.feedback.notifications.invalid_input'),
            })
            ->danger()
            ->send();

        throw new Halt;
    }
}
