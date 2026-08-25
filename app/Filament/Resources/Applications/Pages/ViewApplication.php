<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Actions\MoveApplicationToStatus;
use App\Actions\ScheduleApplicationFitAnalysis;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CriterionEvidenceSource;
use App\Enums\InterviewStatus;
use App\Enums\PhoneCountry;
use App\Enums\SocialNetwork;
use App\Filament\Clusters\Settings\Pages\AiSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\Concerns\ManagesApplicationInterviews;
use App\Filament\Resources\Applications\Pages\Concerns\ManagesInterviewFeedback;
use App\Filament\Resources\Applications\Pages\Concerns\PresentsInterviewEvidence;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\ApplicationCriterionScore;
use App\Models\ApplicationDocument;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\ApplicationUtmParameter;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use LogicException;

class ViewApplication extends ViewRecord
{
    use ManagesApplicationInterviews;
    use ManagesInterviewFeedback;
    use PresentsInterviewEvidence;

    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('applications.admin.title', [
            'candidate' => $this->getApplication()->candidate->name,
        ]);
    }

    public function getBreadcrumb(): string
    {
        return $this->getApplication()->candidate->name;
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        $application = $this->getApplication();

        return [
            JobResource::getUrl(tenant: $application->company) => __('jobs.plural_label'),
            JobResource::getUrl('view', ['record' => $application->job], tenant: $application->company) => $application->job->name,
            '' => $this->getBreadcrumb(),
        ];
    }

    /**
     * Which action leads depends on where the application stands, because only
     * one of them is plausibly the next thing to do. Nothing necessary is
     * hidden: what stops being primary moves into the overflow group.
     */
    protected function getHeaderActions(): array
    {
        $application = $this->getApplication();
        $nextInterview = $this->nextInterview($application);
        $status = $application->status;
        $evaluationFailed = $application->analysis_status === ApplicationAnalysisStatus::Failed;

        $primary = [];
        $secondary = [];

        if ($status->is_terminal) {
            // The process ended. Correcting the stage stays possible; proposing a
            // new interview for a rejected or hired candidate does not — not as a
            // primary action and not buried in the overflow either. Reopening the
            // candidate into an active stage is what makes recruiting actions
            // available again, and that stays a human decision.
            $primary[] = $this->moveStatusAction($application)->color('gray');
        } elseif ($nextInterview !== null) {
            if ($nextInterview->meeting_url !== null) {
                $primary[] = $this->joinInterviewAction($nextInterview);
            }

            $primary[] = $this->moveStatusAction($application)->color('gray');
            $secondary[] = $this->scheduleInterviewAction($application)
                ->label(__('applications.admin.actions.schedule_another_interview'));
        } elseif ($status->is_final_stage) {
            $primary[] = $this->moveStatusAction($application)->color('primary');
            $primary[] = $this->scheduleInterviewAction($application)->color('gray');
        } else {
            // Moving the candidate through the workspace's own workflow leads:
            // an early custom stage (Screening, Assessment, Manager Review) is
            // not an implicit instruction to book an interview. Scheduling stays
            // one click away for when it genuinely is the next step.
            $primary[] = $this->moveStatusAction($application)->color('primary');
            $primary[] = $this->scheduleInterviewAction($application)->color('gray');
        }

        // A terminal application offers no evaluation action at all — not
        // primary, not in the overflow. Re-running an evaluation for somebody
        // who was hired or rejected spends AI allowance on a decision nobody
        // will make again, and the stored evaluation stays readable regardless.
        // A failed evaluation on a live process is a recoverable problem, so its
        // fix surfaces instead of staying buried while it is still relevant.
        if (! $status->is_terminal) {
            if ($evaluationFailed) {
                $primary[] = $this->reprocessApplicationAnalysisAction($application);
            } else {
                $secondary[] = $this->reprocessApplicationAnalysisAction($application);
            }
        }

        return [
            ...$primary,
            ActionGroup::make([
                ...$secondary,
                Action::make('backToPipeline')
                    ->label(__('applications.admin.actions.back_to_pipeline'))
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->url(JobResource::getUrl('view', [
                        'record' => $application->job,
                        'section' => 'pipeline',
                    ], tenant: $application->company)),
                Action::make('openJobWorkspace')
                    ->label(__('applications.admin.actions.open_job_workspace'))
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->url(JobResource::getUrl('view', [
                        'record' => $application->job,
                    ], tenant: $application->company)),
                Action::make('openJobPage')
                    ->label(__('applications.admin.actions.open_job_page'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(route('job.show', ['key' => $application->job->key]))
                    ->openUrlInNewTab(),
            ]),
            // Every action mounted from an interview card — reschedule, cancel,
            // refresh response, record feedback — is deliberately absent from
            // this list. Filament resolves a card-mounted action by looking for
            // a `<name>Action()` method on the page, so listing it here is not
            // what makes it mountable; listing it here and hiding it is what
            // *breaks* it, because `Action::isDisabled()` reports a hidden
            // action as disabled and `mountAction()` refuses to mount a
            // disabled one. Those builders are `protected` rather than
            // `private` for the same reason: the resolver calls them from an
            // ancestor class. Do not "tidy" them back into this list.
        ];
    }

    private function joinInterviewAction(Interview $interview, string $name = 'joinInterview'): Action
    {
        return Action::make($name)
            ->label(__('applications.admin.actions.join_meet'))
            ->icon(Heroicon::OutlinedVideoCamera)
            ->color('success')
            ->url($interview->meeting_url ?? '#')
            ->openUrlInNewTab();
    }

    /**
     * Moving stage is the one recruitment decision this page makes, and it always
     * goes through {@see MoveApplicationToStatus} so tenancy, pipeline integrity
     * and the stage's own communication cannot be bypassed.
     *
     * The name is a parameter because the summary tab offers the same action
     * again as the recommended next step; Filament identifies mounted actions by
     * name, so the second button needs its own.
     */
    private function moveStatusAction(Application $application, string $name = 'moveStatus'): Action
    {
        return Action::make($name)
            ->label(__('applications.admin.actions.move_status'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->schema([
                Select::make('status_id')
                    ->label(__('applications.admin.actions.choose_status'))
                    ->options(fn (): array => $this->statusOptions($application))
                    ->allowHtml()
                    ->default($application->status_id)
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data) use ($application): void {
                Gate::authorize('update', $application);

                $status = Status::query()
                    ->where('company_id', $application->company_id)
                    ->where('pipeline_id', $application->job->pipeline_id)
                    ->findOrFail((int) $data['status_id']);

                app(MoveApplicationToStatus::class)->handle($application, $status);

                $this->record = ApplicationResource::getEloquentQuery()
                    ->findOrFail((int) $application->getKey());

                Notification::make()
                    ->title(__('applications.admin.actions.status_updated'))
                    ->success()
                    ->send();
            });
    }

    private function reprocessApplicationAnalysisAction(Application $application, string $name = 'reprocessApplicationAnalysis'): Action
    {
        $isAwaitingCriteria = $application->analysis_status === ApplicationAnalysisStatus::AwaitingCriteria;

        return Action::make($name)
            ->label($isAwaitingCriteria
                ? __('applications.admin.ai.start_action')
                : __('applications.admin.ai.reprocess_action'))
            ->icon($isAwaitingCriteria ? Heroicon::OutlinedDocumentMagnifyingGlass : Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation(! $isAwaitingCriteria)
            ->modalDescription($isAwaitingCriteria ? null : (string) __('applications.admin.ai.reprocess_confirmation'))
            ->action(function (): void {
                $application = $this->getApplication();

                Gate::authorize('update', $application);

                app(ScheduleApplicationFitAnalysis::class)->handle($application);

                $this->redirect(ApplicationResource::getUrl('view', [
                    'record' => $application,
                    'section' => 'evaluation',
                ], tenant: $application->company), navigate: false);
            });
    }

    /** @return array<int, string> */
    private function statusOptions(Application $application): array
    {
        return Status::query()
            ->where('company_id', $application->company_id)
            ->where('pipeline_id', $application->job->pipeline_id)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Status $status): array => [
                $status->getKey() => view('filament.resources.applications.components.status-option', [
                    'status' => $status,
                ])->render(),
            ])
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        $application = $this->getApplication();

        return $schema->components([
            View::make('filament.resources.applications.components.header')
                ->viewData(['header' => $this->headerData($application)]),
            Tabs::make('application-details-tabs')
                ->tabs([
                    Tab::make(__('applications.admin.tabs.summary'))
                        ->id('summary')
                        ->key('summary')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->schema([
                            $this->nextActionSection($application),
                            View::make('filament.resources.applications.components.summary')
                                ->viewData(['summary' => $this->summaryData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.evaluation'))
                        ->id('evaluation')
                        ->key('evaluation')
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->schema([
                            View::make(@$this->analysisViewName($application))
                                ->viewData(['analysis' => $this->analysisData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.interviews'))
                        ->id('interviews')
                        ->key('interviews')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->badge($this->activeInterviewCount($application))
                        ->schema([
                            View::make('filament.resources.applications.components.interviews')
                                ->viewData(['interviews' => $this->interviewsData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.application'))
                        ->id('application')
                        ->key('application')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->schema([
                            View::make('filament.resources.applications.components.application')
                                ->viewData(['applicationDetails' => $this->applicationData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.documents'))
                        ->id('documents')
                        ->key('documents')
                        ->icon(Heroicon::OutlinedFolderOpen)
                        ->badge($application->documents->count())
                        ->schema([
                            View::make('filament.resources.applications.components.documents')
                                ->viewData(['documents' => $this->documentsData($application)]),
                        ]),
                ])
                ->persistTabInQueryString('section')
                ->columnSpanFull(),
        ]);
    }

    /**
     * The header answers, in order: who, for which job, where in the process,
     * how they scored, and what is already booked. The pipeline stage outranks
     * the evaluation's processing state, which is only a background job.
     *
     * @return array<string, mixed>
     */
    private function headerData(Application $application): array
    {
        $analysisStatus = $this->evaluationStateKey($application);
        $fit = $this->fitSummary($application);
        $nextInterview = $this->nextInterview($application);

        return [
            'candidate_name' => $application->candidate->name,
            'candidate_url' => CandidateResource::getUrl('view', [
                'record' => $application->candidate,
            ], tenant: $application->company),
            'candidate_initials' => Str::of($application->candidate->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
                ->join(''),
            'job' => $application->job->name,
            'job_url' => JobResource::getUrl('view', [
                'record' => $application->job,
            ], tenant: $application->company),
            'status' => $application->status->name,
            'status_color' => $application->status->color,
            'stage_role' => $this->stageRole($application),
            'score' => $fit['score'],
            'coverage' => $fit['coverage'],
            'needs_validation_count' => $fit['needs_validation_count'],
            'analysis_status' => $analysisStatus,
            'analysis_label' => __("applications.admin.ai.states.{$analysisStatus}.label"),
            'analysis_color' => $this->analysisColor($analysisStatus),
            'next_interview' => $nextInterview === null ? null : [
                'scheduled_at' => $nextInterview->scheduled_at
                    ->setTimezone($nextInterview->timezone)
                    ->translatedFormat('M j, Y · H:i'),
                'meeting_url' => $nextInterview->meeting_url,
            ],
        ];
    }

    /**
     * The recruiter's likely next step, with the action that performs it right
     * there. A sentence on its own would send them looking for the button.
     *
     * It remains a recommendation: the recruiter presses it, and a terminal
     * outcome offers no recruiting action at all.
     */
    private function nextActionSection(Application $application): Section
    {
        $key = $this->nextActionKey($application, $this->nextInterview($application));
        $actions = $this->nextActionActions($application, $key);

        // The heading stays "likely next step", never an instruction: the
        // recommendation is guidance, and the recruiter is the one who acts.
        return Section::make(__('applications.admin.summary.next_action_heading'))
            ->icon($this->nextActionIcon($key))
            ->columnSpanFull()
            ->schema([
                Text::make(__("applications.admin.summary.next_actions.{$key}.title"))
                    ->weight(FontWeight::SemiBold),
                Text::make(__("applications.admin.summary.next_actions.{$key}.description"))
                    ->color('gray'),
                ...($actions === [] ? [] : [Actions::make($actions)->key('application-next-action')]),
            ]);
    }

    /**
     * Every branch reuses the page's existing actions rather than restating any
     * rule: the same handler, the same authorization, a distinct action name so
     * both buttons can be mounted.
     *
     * @return list<Action>
     */
    private function nextActionActions(Application $application, string $key): array
    {
        $nextInterview = $this->nextInterview($application);

        return match ($key) {
            'review_candidate' => [
                $this->moveStatusAction($application, 'nextActionMoveStatus')->color('primary'),
                $this->openTabAction('nextActionOpenEvaluation', 'evaluation', Heroicon::OutlinedClipboardDocumentCheck),
                $this->scheduleInterviewAction($application, 'nextActionScheduleInterview')->color('gray'),
            ],
            'prepare_interview' => array_values(array_filter([
                $nextInterview?->meeting_url === null
                    ? null
                    : $this->joinInterviewAction($nextInterview, 'nextActionJoinInterview'),
                $this->openTabAction('nextActionOpenInterviews', 'interviews', Heroicon::OutlinedCalendarDays),
                $this->openTabAction('nextActionOpenBrief', 'evaluation', Heroicon::OutlinedClipboardDocumentCheck),
            ])),
            'decide' => [
                $this->moveStatusAction($application, 'nextActionMoveStatus')->color('primary'),
                $this->scheduleInterviewAction($application, 'nextActionScheduleInterview')->color('gray'),
            ],
            'evaluation_failed' => [
                $this->reprocessApplicationAnalysisAction($application, 'nextActionReprocessAnalysis')->color('primary'),
                $this->moveStatusAction($application, 'nextActionMoveStatus')->color('gray'),
            ],
            'evaluation_blocked' => [
                Action::make('nextActionReviewAiUsage')
                    ->label(__('settings.topbar.manage_ai'))
                    ->icon(Heroicon::OutlinedBolt)
                    ->color('primary')
                    ->url(AiSettings::getUrl(tenant: $application->company)),
                $this->openTabAction('nextActionOpenEvaluation', 'evaluation', Heroicon::OutlinedClipboardDocumentCheck),
            ],
            'await_evaluation' => [
                $this->openTabAction('nextActionOpenEvaluation', 'evaluation', Heroicon::OutlinedClipboardDocumentCheck),
            ],
            // The evaluation is blocked on a decision nobody has made yet: the
            // job's criteria still need reviewing and confirming.
            'awaiting_criteria' => array_values(array_filter([
                JobResource::canEdit($application->job)
                    ? Action::make('nextActionReviewJobCriteria')
                        ->label(__('jobs.criteria.confirm_action'))
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->color('primary')
                        ->url(JobResource::getUrl('edit', [
                            'record' => $application->job,
                            'activeJobEditTab' => 'ai-criteria',
                        ], tenant: $application->company))
                    : null,
                $this->moveStatusAction($application, 'nextActionMoveStatus')->color('gray'),
            ])),
            // 'hired' and 'closed': the outcome is the answer, so no recruiting
            // action is offered.
            default => [],
        };
    }

    private function openTabAction(string $name, string $section, Heroicon $icon): Action
    {
        $application = $this->getApplication();

        return Action::make($name)
            ->label(__("applications.admin.tabs.{$section}"))
            ->icon($icon)
            ->color('gray')
            ->url(ApplicationResource::getUrl('view', [
                'record' => $application,
                'section' => $section,
            ], tenant: $application->company));
    }

    private function nextActionIcon(string $key): Heroicon
    {
        return match ($key) {
            'hired' => Heroicon::OutlinedCheckBadge,
            'closed' => Heroicon::OutlinedArchiveBox,
            'prepare_interview' => Heroicon::OutlinedCalendarDays,
            'decide' => Heroicon::OutlinedHandRaised,
            'awaiting_criteria' => Heroicon::OutlinedClipboardDocumentCheck,
            'review_candidate' => Heroicon::OutlinedUserCircle,
            'evaluation_failed', 'evaluation_blocked' => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedFlag,
        };
    }

    /**
     * The process-first view of this application: stage, how long they have been
     * in it, fit and commitment.
     *
     * There is deliberately no "stage 3 of 6" and no left-to-right chain of every
     * stage: hired and rejected are alternative outcomes, not steps a candidate
     * passes through, so a linear indicator would describe a process that does
     * not exist. The current stage, and how long they have been waiting in it, is
     * what a recruiter actually needs.
     *
     * @return array<string, mixed>
     */
    private function summaryData(Application $application): array
    {
        $fit = $this->fitSummary($application);
        $nextInterview = $this->nextInterview($application);
        $analysisStatus = $this->enumValue($application->analysis_status);
        $daysInStage = $application->daysInCurrentStage();
        $threshold = $application->status->attention_after_days;

        return [
            'stage' => [
                'name' => $application->status->name,
                'color' => $application->status->color,
                'role' => $this->stageRole($application),
                'entered_at' => $application->statusEnteredAt()->translatedFormat('M j, Y · H:i'),
                'age' => trans_choice('attention.days', $daysInStage, ['count' => $daysInStage]),
                'is_overdue' => $application->isOverdueInCurrentStage(),
                'threshold' => $threshold === null
                    ? null
                    : trans_choice('attention.days', $threshold, ['count' => $threshold]),
            ],
            'fit' => [
                'status' => $analysisStatus,
                'label' => __("applications.admin.ai.states.{$this->evaluationStateKey($application)}.label"),
                'score' => $fit['score'],
                'coverage' => $fit['coverage'],
                'needs_validation_count' => $fit['needs_validation_count'],
                'supported_count' => $fit['supported_count'],
                'url' => ApplicationResource::getUrl('view', [
                    'record' => $application,
                    'section' => 'evaluation',
                ], tenant: $application->company),
            ],
            'interview' => $nextInterview === null ? null : [
                'scheduled_at' => $nextInterview->scheduled_at
                    ->setTimezone($nextInterview->timezone)
                    ->translatedFormat('M j, Y · H:i'),
                'timezone' => $nextInterview->timezone,
                'meeting_url' => $nextInterview->meeting_url,
                'rsvp' => __("applications.admin.interviews.rsvp.{$nextInterview->rsvp_status->value}"),
                'url' => ApplicationResource::getUrl('view', [
                    'record' => $application,
                    'section' => 'interviews',
                ], tenant: $application->company),
            ],
            'applied_at' => $application->created_at->translatedFormat('M j, Y · H:i'),
        ];
    }

    /**
     * Fit, evidence coverage and confidence, read from the stored evaluation.
     *
     * Three separate numbers, deliberately never merged. Fit is the weighted
     * average of the criteria that could be assessed. Coverage is how much of the
     * weighted criteria the application actually allowed to be assessed at all.
     * Confidence is how strongly the submitted material supports each assessment.
     * A single "confidence-adjusted fit" would hide exactly the thing a recruiter
     * needs to see.
     *
     * Nothing is returned unless the evaluation is the *current* one: a fit
     * measured against criteria the job has since changed is not an answer to
     * "how does this candidate match this job".
     *
     * @return array{score: int|null, coverage: int|null, needs_validation_count: int, supported_count: int}
     */
    private function fitSummary(Application $application): array
    {
        if (! $application->hasCurrentEvaluation()) {
            return ['score' => null, 'coverage' => null, 'needs_validation_count' => 0, 'supported_count' => 0];
        }

        $application->loadMissing('criterionScores');

        return [
            'score' => $application->analysis_score === null
                ? null
                : (int) round((float) $application->analysis_score),
            'coverage' => $application->analysis_coverage,
            'needs_validation_count' => $application->criterionScores
                ->filter(fn (ApplicationCriterionScore $score): bool => $this->needsValidation($score)
                    && $this->importanceForWeight($score->weight) === 'high')
                ->count(),
            'supported_count' => $application->criterionScores
                ->reject(fn (ApplicationCriterionScore $score): bool => $this->needsValidation($score))
                ->count(),
        ];
    }

    /**
     * A criterion needs human validation when the application either could not
     * support a judgement at all, or supported it only weakly. Both are
     * uncertainty; neither is a verdict on the candidate.
     */
    private function needsValidation(ApplicationCriterionScore $score): bool
    {
        return ! $score->isAssessed() || $score->confidence->value !== 'high';
    }

    /**
     * The most plausible next recruiting step, given where the application
     * currently stands. It is a suggestion in the UI, never an automation.
     */
    private function nextActionKey(Application $application, ?Interview $nextInterview): string
    {
        $analysisStatus = $this->enumValue($application->analysis_status);

        return match (true) {
            $application->status->is_hired => 'hired',
            // Rejected, withdrawn, disqualified: the process ended, so the page
            // must stop proposing recruiting steps for this candidate.
            $application->status->is_terminal => 'closed',
            $nextInterview !== null => 'prepare_interview',
            // A candidate in a late stage is waiting on a decision, and that
            // outranks the evaluation: the evaluation is evidence, and by this
            // point the recruiter has interviewed the person themselves.
            $application->status->is_final_stage => 'decide',
            $analysisStatus === 'failed' => 'evaluation_failed',
            $analysisStatus === 'pending_quota' => 'evaluation_blocked',
            $analysisStatus === 'awaiting_criteria' => 'awaiting_criteria',
            in_array($analysisStatus, ['pending', 'processing'], strict: true) => 'await_evaluation',
            // Deliberately not "schedule an interview". Workspaces run their own
            // workflows — Applied, Screening, Assessment, Manager Review — and
            // "no interview exists" is not evidence that an interview is the next
            // step. Reviewing the candidate and moving them along their own
            // pipeline is the step that is always true.
            default => 'review_candidate',
        };
    }

    /**
     * How the current stage reads in the process: the definitive hire, a
     * process closed without one, a late stage, or none of those.
     */
    private function stageRole(Application $application): ?string
    {
        return match (true) {
            $application->status->is_hired => 'hired',
            $application->status->is_terminal => 'closed',
            $application->status->is_final_stage => 'final_stage',
            default => null,
        };
    }

    /** The soonest interview that has neither been cancelled nor already ended. */
    private function nextInterview(Application $application): ?Interview
    {
        $application->loadMissing('interviews');

        return $application->interviews
            ->filter(fn (Interview $interview): bool => $interview->status !== InterviewStatus::Cancelled
                && $interview->ends_at->isFuture())
            ->sortBy('scheduled_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationData(Application $application): array
    {
        $coverLetterType = $this->enumValue($application->cover_letter_type);

        $source = $this->enumValue($application->source);

        return [
            'candidate_name' => $application->candidate->name,
            'candidate_email' => $application->candidate->email ?? __('applications.admin.not_provided'),
            'candidate_phone' => PhoneCountry::formatInternational($application->candidate->phone)
                ?? __('applications.admin.not_provided'),
            'socials' => $this->socialProfiles($application->candidate),
            'origin' => [
                'source' => __("applications.admin.sources.{$source}"),
                'referral' => $application->referral?->user->name ?? __('applications.admin.not_applicable'),
                'submitted_ip' => $application->submitted_ip ?? __('applications.admin.not_provided'),
                'utms' => $application->utmParameters
                    ->map(fn (ApplicationUtmParameter $parameter): array => [
                        'name' => $parameter->name,
                        'value' => $parameter->value,
                    ])
                    ->all(),
            ],
            'cover_letter_type' => __("applications.admin.cover_letter.types.{$coverLetterType}"),
            'cover_letter_text' => $application->cover_letter_text,
            'answers' => $application->answers->map(function (ApplicationAnswer $answer): array {
                $responseType = $this->enumValue($answer->response_type);
                $value = $responseType === 'number'
                    ? ($answer->value_number === null ? null : (string) $answer->value_number)
                    : $answer->value_text;

                return [
                    'question' => $answer->question_snapshot,
                    'type' => __("jobs.application.question_types.{$responseType}"),
                    'value' => filled($value) ? $value : __('applications.admin.not_answered'),
                ];
            })->all(),
        ];
    }

    /**
     * @return list<array<string, bool|int|string|null>>
     */
    private function documentsData(Application $application): array
    {
        $documents = $application->documents
            ->sortBy(fn (ApplicationDocument $document): string => $this->enumValue($document->type))
            ->map(function (ApplicationDocument $document) use ($application): array {
                $type = $this->enumValue($document->type);

                return [
                    'type' => (string) __("applications.admin.documents.types.{$type}"),
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'extension' => Str::upper($document->extension),
                    'size' => Number::fileSize($document->size),
                    'uploaded_at' => $this->formatDate($document->getAttribute('uploaded_at'))
                        ?? $document->created_at->translatedFormat('M j, Y · H:i'),
                    'can_preview' => Str::lower($document->extension) === 'pdf',
                    'view_url' => route('application-documents.view', [
                        'company' => $application->company,
                        'application' => $application,
                        'document' => $document,
                    ]),
                    'download_url' => route('application-documents.download', [
                        'company' => $application->company,
                        'application' => $application,
                        'document' => $document,
                    ]),
                ];
            })
            ->values()
            ->all();

        return array_values($documents);
    }

    /**
     * The evaluation state as the recruiter experiences it, which is not always
     * the stored status: a completed evaluation whose criteria have moved on is
     * `outdated`, because presenting it as "complete" would be presenting a
     * measurement of criteria that no longer govern this hiring process.
     */
    private function evaluationStateKey(Application $application): string
    {
        return $application->hasOutdatedEvaluation()
            ? 'outdated'
            : $this->enumValue($application->analysis_status);
    }

    /** @return view-string */
    private function analysisViewName(Application $application): string
    {
        return match ($this->evaluationStateKey($application)) {
            'pending' => 'filament.resources.applications.components.ai-analysis-pending',
            'processing' => 'filament.resources.applications.components.ai-analysis-processing',
            'completed' => 'filament.resources.applications.components.ai-analysis-completed',
            'failed' => 'filament.resources.applications.components.ai-analysis-failed',
            'pending_quota' => 'filament.resources.applications.components.ai-analysis-pending-quota',
            'outdated' => 'filament.resources.applications.components.ai-analysis-outdated',
            default => 'filament.resources.applications.components.ai-analysis-awaiting-criteria',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisData(Application $application): array
    {
        $status = $this->evaluationStateKey($application);

        $data = [
            'status' => $status,
            'label' => __("applications.admin.ai.states.{$status}.label"),
            'title' => __("applications.admin.ai.states.{$status}.title"),
            'description' => __("applications.admin.ai.states.{$status}.description"),
            'icon' => $this->analysisIcon($status),
            'received_at' => $application->created_at->translatedFormat('M j, Y · H:i'),
        ];

        if ($status !== 'completed') {
            return $data;
        }

        $application->loadMissing(['criterionScores', 'interviewBriefItems']);

        // The two evidence layers live on two tabs. This one shows only what the
        // submitted application supported, and points at the human interview
        // evidence instead of repeating it.
        $data['interview_evidence_url'] = ApplicationResource::getUrl('view', [
            'record' => $application,
            'section' => 'interviews',
        ], tenant: $application->company);

        $data['score'] = $application->analysis_score !== null
            ? (int) round((float) $application->analysis_score)
            : null;
        $data['coverage'] = $application->analysis_coverage;
        $data['analyzed_at'] = $application->analyzed_at?->translatedFormat('M j, Y · H:i');

        $criteria = $application->criterionScores
            ->map(fn (ApplicationCriterionScore $score): array => [
                'criterion' => $score->criterion,
                'score' => $score->score,
                'is_assessed' => $score->isAssessed(),
                'weight' => $score->weight,
                'importance' => $this->importanceForWeight($score->weight),
                'reason' => $score->reason,
                'confidence' => $score->confidence->value,
                'confidence_rank' => $this->confidenceRank($score->confidence->value),
                'evidence' => $this->evidenceFor($score),
            ])
            ->values();

        // Unassessed criteria sit at the top of the validation list: the most
        // useful thing the evaluation can tell a recruiter is what it could not
        // establish, not which criterion scored lowest.
        $needsValidation = $criteria
            ->filter(fn (array $criterion): bool => ! $criterion['is_assessed'] || $criterion['confidence'] !== 'high')
            ->sortBy([
                ['is_assessed', 'asc'],
                ['weight', 'desc'],
                ['confidence_rank', 'asc'],
                ['criterion', 'asc'],
            ])
            ->values()
            ->all();
        $supported = $criteria
            ->filter(fn (array $criterion): bool => $criterion['is_assessed'] && $criterion['confidence'] === 'high')
            ->sortBy([
                ['weight', 'desc'],
                ['criterion', 'asc'],
            ])
            ->values()
            ->all();

        $data['criteria'] = [
            'needs_validation' => $needsValidation,
            'supported' => $supported,
            'needs_validation_count' => collect($needsValidation)
                ->where('importance', 'high')
                ->count(),
            'unassessed_count' => collect($needsValidation)
                ->where('is_assessed', false)
                ->count(),
            'supported_count' => count($supported),
        ];

        $data['interview_brief_items'] = $application->interviewBriefItems
            ->map(fn (ApplicationInterviewBriefItem $item): array => [
                'criterion' => $item->criterion,
                'priority' => $item->priority,
                'reason' => $item->reason,
                'question' => $item->question,
            ])
            ->all();

        return $data;
    }

    /**
     * The concrete support the evaluation found for a criterion, and where in the
     * submitted application it found it. This is what lets "supported by
     * application evidence" be checked rather than believed — and it is support
     * found in what the candidate submitted, never external verification.
     *
     * @return list<array{source: string, detail: string}>
     */
    private function evidenceFor(ApplicationCriterionScore $score): array
    {
        return array_map(fn (array $item): array => [
            // Every stored source came through the enum on the way in; the
            // fallback only covers a value retired from the enum later.
            'source' => (string) (CriterionEvidenceSource::tryFrom($item['source'])?->label()
                ?? __('applications.admin.ai.evidence.sources.application_answer')),
            'detail' => $item['detail'],
        ], $score->evidence ?? []);
    }

    private function analysisIcon(string $status): string
    {
        return match ($status) {
            'processing' => 'heroicon-o-arrow-path',
            'completed' => 'heroicon-o-document-magnifying-glass',
            'failed' => 'heroicon-o-x-circle',
            'pending_quota' => 'heroicon-o-bolt-slash',
            'outdated' => 'heroicon-o-arrow-path',
            'awaiting_criteria' => 'heroicon-o-clipboard-document-check',
            default => 'heroicon-o-clock',
        };
    }

    private function analysisColor(string $status): string
    {
        return match ($status) {
            'processing' => 'info',
            'completed' => 'gray',
            'failed' => 'danger',
            'pending_quota' => 'warning',
            'outdated' => 'warning',
            default => 'gray',
        };
    }

    private function importanceForWeight(int $weight): string
    {
        return match (true) {
            $weight >= 7 => 'high',
            $weight >= 4 => 'medium',
            default => 'low',
        };
    }

    private function confidenceRank(string $confidence): int
    {
        return match ($confidence) {
            'low' => 0,
            'medium' => 1,
            default => 2,
        };
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @return list<array{label: string, account: string, url: string|null}>
     */
    private function socialProfiles(Candidate $candidate): array
    {
        $socials = $candidate->getAttribute('socials');

        if (! is_array($socials)) {
            return [];
        }

        $profiles = [];

        foreach ($socials as $social) {
            if (! is_array($social)) {
                continue;
            }

            $network = is_string($social['network'] ?? null) ? $social['network'] : 'other';
            $account = is_string($social['account'] ?? null) ? $social['account'] : '';

            if ($account === '') {
                continue;
            }

            $profiles[] = [
                'label' => SocialNetwork::tryFrom($network)?->label() ?? Str::headline($network),
                'account' => $account,
                'url' => filter_var($account, FILTER_VALIDATE_URL) ? $account : null,
            ];
        }

        return $profiles;
    }

    private function formatDate(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->translatedFormat('M j, Y · H:i') : null;
    }

    private function getApplication(): Application
    {
        $record = $this->getRecord();

        if (! $record instanceof Application) {
            throw new LogicException('The application view page must be bound to an application.');
        }

        return $record;
    }
}
