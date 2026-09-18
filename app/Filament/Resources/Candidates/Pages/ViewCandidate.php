<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Actions\GenerateCandidateCommunicationDraft;
use App\Actions\SetCandidateDoNotContact;
use App\Enums\ApplicationLocale;
use App\Enums\CandidateCommunicationDraftPurpose;
use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\CandidateMaterialPreparationStatus;
use App\Enums\InterviewStatus;
use App\Enums\PhoneCountry;
use App\Enums\SocialNetwork;
use App\Exceptions\CandidateCommunicationException;
use App\Filament\Clusters\Settings\Pages\EmailProviderSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\CandidateMaterial;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use App\Services\CandidateCommunicationService;
use App\Services\CandidateMaterialLifecycle;
use App\Services\CandidateMaterialPreparation;
use App\Services\CandidateMaterialStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * A person, across every process they take part in. It deliberately does not
 * repeat the application workspace: each row links out to it.
 */
class ViewCandidate extends ViewRecord
{
    protected static string $resource = CandidateResource::class;

    public ?int $communicationJobId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $jobId = request()->integer('communicationJob');

        $this->communicationJobId = $jobId > 0 ? $jobId : null;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getCandidate()->name;
    }

    protected function getHeaderActions(): array
    {
        $groupedActions = [];
        $actions = [];

        if ($this->communicationJob() instanceof Job) {
            $actions[] = $this->composeCommunicationAction();

            $groupedActions[] = $this->prepareCommunicationDraftAction();

            if ($this->hasEligibleFollowUpContext()) {
                $groupedActions[] = $this->prepareCommunicationDraftAction(CandidateCommunicationDraftPurpose::FollowUp);
            }
        }

        $groupedActions[] = $this->doNotContactAction();
        $groupedActions[] = $this->addCvAction();

        $actions[] = ActionGroup::make($groupedActions)
            ->icon(Heroicon::OutlinedEllipsisVertical)
            ->color('gray');

        $actions[] = EditAction::make();

        return $actions;
    }

    private function doNotContactAction(): Action
    {
        $candidate = $this->getCandidate();

        return Action::make('toggleDoNotContact')
            ->label($candidate->isDoNotContact()
                ? __('communications.actions.allow_contact')
                : __('communications.actions.do_not_contact'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color($candidate->isDoNotContact() ? 'gray' : 'danger')
            ->requiresConfirmation()
            ->modalDescription($candidate->isDoNotContact()
                ? __('communications.dnc.allow_description')
                : __('communications.dnc.block_description'))
            ->action(function (SetCandidateDoNotContact $doNotContact) use ($candidate): void {
                $isDoNotContact = ! $candidate->isDoNotContact();

                $doNotContact->run($this->getCurrentUser(), $candidate, $isDoNotContact);
                $this->refreshCandidateRecord();

                Notification::make()
                    ->title($isDoNotContact
                        ? __('communications.notifications.do_not_contact_set')
                        : __('communications.notifications.contact_allowed'))
                    ->success()
                    ->send();
            });
    }

    private function composeCommunicationAction(): Action
    {
        return Action::make('composeCommunication')
            ->label(__('communications.actions.contact_candidate'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalHeading(fn (): string => __('communications.composer.heading', ['candidate' => $this->getCandidate()->name]))
            ->modalDescription(fn (): string => $this->composerDescription())
            ->modalSubmitActionLabel(__('communications.actions.send'))
            ->modalSubmitAction(fn (Action $action): Action => $action->disabled(
                fn (): bool => $this->getCandidate()->isDoNotContact()
                    || ! $this->candidateHasValidEmail()
                    || ! $this->hasUsableEmailProvider(),
            ))
            ->extraModalFooterActions(function (Action $action): array {
                $actions = [
                    $action->makeModalSubmitAction('prepareCommunicationOutreachFromComposer', arguments: [
                        'draftPurpose' => CandidateCommunicationDraftPurpose::InitialOutreach->value,
                    ])
                        ->label(__('communications.actions.prepare_outreach'))
                        ->icon(Heroicon::OutlinedSparkles)
                        ->color('gray')
                        ->disabled(fn (): bool => $this->getCandidate()->isDoNotContact()),
                    $action->makeModalSubmitAction('saveCommunicationDraft', arguments: ['saveDraft' => true])
                        ->label(__('communications.actions.save_draft'))
                        ->color('gray'),
                    $action->makeModalSubmitAction('discardCommunicationDraft', arguments: ['discardDraft' => true])
                        ->label(__('communications.actions.discard_draft'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => $this->communicationDraft() instanceof CandidateCommunicationMessage),
                ];

                if ($this->hasEligibleFollowUpContext()) {
                    $actions[] = $action->makeModalSubmitAction('prepareCommunicationFollowUpFromComposer', arguments: [
                        'draftPurpose' => CandidateCommunicationDraftPurpose::FollowUp->value,
                    ])
                        ->label(__('communications.actions.prepare_follow_up'))
                        ->icon(Heroicon::OutlinedSparkles)
                        ->color('gray')
                        ->disabled(fn (): bool => $this->getCandidate()->isDoNotContact());
                }

                if (! $this->hasUsableEmailProvider()) {
                    $actions[] = Action::make('configureEmailProvider')
                        ->label(__('communications.actions.open_email_provider_settings'))
                        ->url(EmailProviderSettings::getUrl(tenant: $this->getCompany()))
                        ->color('gray');
                }

                return $actions;
            })
            ->fillForm(fn (): array => $this->composerFormState())
            ->schema([
                Hidden::make('draft_id'),
                TextInput::make('recipient')
                    ->label(__('communications.fields.recipient'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sender')
                    ->label(__('communications.fields.sender'))
                    ->placeholder(__('communications.composer.sender_unavailable'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('subject')
                    ->label(__('communications.fields.subject'))
                    ->maxLength(160),
                Textarea::make('body')
                    ->label(__('communications.fields.body'))
                    ->rows(12)
                    ->maxLength(1800),
            ])
            ->action(function (array $data, array $arguments, Schema $schema, CandidateCommunicationService $communications, GenerateCandidateCommunicationDraft $drafts): void {
                $candidate = $this->getCandidate();
                $thread = $communications->resolveThread(
                    $this->getCurrentUser(),
                    $this->getCompany(),
                    $candidate,
                    $this->communicationJobOrFail(),
                );

                // Every branch below that keeps the composer open must throw
                // Halt: Filament unmounts the action and resets its data once
                // the closure returns normally, which would discard whatever
                // the recruiter is still reviewing.
                $purpose = CandidateCommunicationDraftPurpose::tryFrom((string) ($arguments['draftPurpose'] ?? ''));

                if ($purpose instanceof CandidateCommunicationDraftPurpose) {

                    try {
                        $drafts->handle(
                            $this->getCurrentUser(),
                            $thread,
                            $this->defaultCommunicationLocale(),
                            $purpose,
                        );
                    } catch (CandidateCommunicationException $exception) {
                        $this->notifyCommunicationException($exception);

                        throw new Halt;
                    }

                    $schema->fill($this->composerFormState());

                    $this->refreshCandidateRecord();

                    Notification::make()->title(__('communications.notifications.draft_prepared'))->success()->send();

                    throw new Halt;
                }

                $draftId = $data['draft_id'] ?? null;
                $draft = is_numeric($draftId)
                    ? $thread->messages()->whereKey((int) $draftId)->first()
                    : null;

                if ($arguments['discardDraft'] ?? false) {
                    if (! $draft instanceof CandidateCommunicationMessage) {
                        Notification::make()->title(__('communications.errors.unavailable'))->danger()->send();

                        return;
                    }

                    try {
                        $communications->discardDraft($this->getCurrentUser(), $draft);
                    } catch (CandidateCommunicationException $exception) {
                        $this->notifyCommunicationException($exception);

                        return;
                    }

                    $this->refreshCandidateRecord();

                    Notification::make()->title(__('communications.notifications.draft_discarded'))->success()->send();

                    return;
                }

                if (! ($arguments['saveDraft'] ?? false)
                    && (blank($data['subject'] ?? null) || blank($data['body'] ?? null))) {
                    Notification::make()->title(__('communications.errors.subject_and_body_required'))->danger()->send();

                    throw new Halt;
                }

                try {
                    if ($draft instanceof CandidateCommunicationMessage) {
                        $draft = $communications->updateDraft(
                            $this->getCurrentUser(),
                            $draft,
                            (string) $data['subject'],
                            (string) $data['body'],
                        );
                    } else {
                        $draft = $communications->createDraft(
                            $this->getCurrentUser(),
                            $thread,
                            (string) $data['subject'],
                            (string) $data['body'],
                        );
                    }

                    if ($arguments['saveDraft'] ?? false) {
                        $this->refreshCandidateRecord();

                        Notification::make()->title(__('communications.notifications.draft_saved'))->success()->send();

                        return;
                    }

                    $communications->authorizeDraft($this->getCurrentUser(), $draft);
                } catch (CandidateCommunicationException $exception) {
                    $this->notifyCommunicationException($exception);

                    return;
                }

                $this->refreshCandidateRecord();

                Notification::make()->title(__('communications.notifications.send_requested'))->success()->send();
            });
    }

    public function prepareCommunicationDraftAction(
        CandidateCommunicationDraftPurpose $purpose = CandidateCommunicationDraftPurpose::InitialOutreach,
    ): Action {
        $isFollowUp = $purpose === CandidateCommunicationDraftPurpose::FollowUp;

        return Action::make($isFollowUp ? 'prepareCommunicationFollowUp' : 'prepareCommunicationDraft')
            ->label($isFollowUp
                ? __('communications.actions.prepare_follow_up')
                : __('communications.actions.prepare_outreach'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->schema([
                Select::make('language')
                    ->label(__('communications.fields.language'))
                    ->options(ApplicationLocale::options())
                    ->default(fn (): string => $this->defaultCommunicationLocale())
                    ->required()
                    ->native(false),
            ])
            ->disabled(fn (): bool => $this->getCandidate()->isDoNotContact())
            ->action(function (array $data, CandidateCommunicationService $communications, GenerateCandidateCommunicationDraft $drafts) use ($purpose): void {
                try {
                    $thread = $communications->resolveThread(
                        $this->getCurrentUser(),
                        $this->getCompany(),
                        $this->getCandidate(),
                        $this->communicationJobOrFail(),
                    );
                    $drafts->handle(
                        $this->getCurrentUser(),
                        $thread,
                        ApplicationLocale::from((string) $data['language']),
                        $purpose,
                    );
                } catch (CandidateCommunicationException $exception) {
                    $this->notifyCommunicationException($exception);

                    return;
                }

                $this->refreshCandidateRecord();

                Notification::make()->title(__('communications.notifications.draft_prepared'))->success()->send();
            });
    }

    /**
     * A single independent CV, attached to this already-selected candidate.
     * No email is required and nothing read from the file ever updates the
     * candidate's profile: the declaration recorded here is the importer's
     * own statement that the workspace may hold and use this document, not a
     * claim about the candidate's consent or the document's authenticity.
     */
    private function addCvAction(): Action
    {
        return Action::make('addCv')
            ->label(__('candidates.materials.actions.add_cv'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->schema([
                FileUpload::make('file')
                    ->label(__('candidates.materials.actions.file'))
                    ->storeFiles(false)
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->required(),
                TextInput::make('source_label')
                    ->label(__('candidates.materials.actions.source_label'))
                    ->required()
                    ->maxLength(120),
                DatePicker::make('received_on')
                    ->label(__('candidates.materials.actions.received_on'))
                    ->native(false)
                    ->maxDate(now()),
                Checkbox::make('declaration')
                    ->label(__('candidates.materials.actions.declaration'))
                    ->accepted()
                    ->required(),
            ])
            ->modalHeading(__('candidates.materials.actions.add_cv_heading'))
            ->modalSubmitActionLabel(__('candidates.materials.actions.add_cv_confirm'))
            ->action(function (array $data, CandidateMaterialStorage $storage): void {
                $candidate = $this->getCandidate();
                $company = $this->getCompany();
                $user = $this->getCurrentUser();

                Gate::forUser($user)->authorize('update', $candidate);

                $file = $data['file'] ?? null;

                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'file' => __('candidates.materials.actions.file_required'),
                    ]);
                }

                $result = $storage->store(
                    $company,
                    $candidate,
                    $user,
                    $file,
                    (string) $data['source_label'],
                    filled($data['received_on'] ?? null) ? (string) $data['received_on'] : null,
                    (bool) ($data['declaration'] ?? false),
                );

                $this->refreshCandidateRecord();

                if ($result['duplicate']) {
                    $material = $result['material'];

                    Notification::make()
                        ->title(__($material->archived_at !== null
                            ? 'candidates.materials.notifications.duplicate_archived'
                            : 'candidates.materials.notifications.duplicate'))
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('candidates.materials.notifications.added'))
                    ->success()
                    ->send();
            });
    }

    public function archiveMaterialAction(): Action
    {
        return Action::make('archiveMaterial')
            ->label(__('candidates.materials.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->requiresConfirmation()
            ->action(function (array $arguments, CandidateMaterialLifecycle $lifecycle): void {
                $material = $this->resolveMaterial($arguments, 'update');
                $lifecycle->archive($this->getCompany(), $material, $this->getCurrentUser(), true);
                $this->refreshCandidateRecord();

                Notification::make()->title(__('candidates.materials.notifications.archived'))->success()->send();
            });
    }

    public function restoreMaterialAction(): Action
    {
        return Action::make('restoreMaterial')
            ->label(__('candidates.materials.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->action(function (array $arguments, CandidateMaterialLifecycle $lifecycle): void {
                $material = $this->resolveMaterial($arguments, 'update');
                $lifecycle->archive($this->getCompany(), $material, $this->getCurrentUser(), false);
                $this->refreshCandidateRecord();

                Notification::make()->title(__('candidates.materials.notifications.restored'))->success()->send();
            });
    }

    public function retryMaterialPreparationAction(): Action
    {
        return Action::make('retryMaterialPreparation')
            ->label(__('candidates.materials.actions.retry'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function (array $arguments, CandidateMaterialPreparation $preparation): void {
                $material = $this->resolveMaterial($arguments, 'update');
                $preparation->retry($this->getCompany(), $material, $this->getCurrentUser());
                $this->refreshCandidateRecord();

                Notification::make()->title(__('candidates.materials.notifications.retry_scheduled'))->success()->send();
            });
    }

    /**
     * Only the source label and the declared received date are editable
     * here: the original adding attribution and timestamp are immutable, and
     * the service records who corrected the metadata and when.
     */
    public function correctMaterialMetadataAction(): Action
    {
        return Action::make('correctMaterialMetadata')
            ->label(__('candidates.materials.actions.correct_metadata'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                TextInput::make('source_label')
                    ->label(__('candidates.materials.actions.source_label'))
                    ->required()
                    ->maxLength(120),
                DatePicker::make('received_on')
                    ->label(__('candidates.materials.actions.received_on'))
                    ->native(false)
                    ->maxDate(now()),
            ])
            ->fillForm(function (array $arguments): array {
                $material = $this->resolveMaterial($arguments, 'update');

                return [
                    'source_label' => $material->source_label,
                    'received_on' => $material->received_on?->toDateString(),
                ];
            })
            ->modalHeading(__('candidates.materials.actions.correct_metadata_heading'))
            ->action(function (array $data, array $arguments, CandidateMaterialLifecycle $lifecycle): void {
                $material = $this->resolveMaterial($arguments, 'update');
                $lifecycle->correctMetadata(
                    $this->getCompany(),
                    $material,
                    $this->getCurrentUser(),
                    (string) $data['source_label'],
                    filled($data['received_on'] ?? null) ? (string) $data['received_on'] : null,
                );
                $this->refreshCandidateRecord();

                Notification::make()->title(__('candidates.materials.notifications.metadata_corrected'))->success()->send();
            });
    }

    /**
     * Deleting a material removes only that independent document and its
     * derived text — it never deletes the candidate, and it never touches
     * documents separately retained on an application.
     */
    public function deleteMaterialAction(): Action
    {
        return Action::make('deleteMaterial')
            ->label(__('candidates.materials.actions.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('candidates.materials.actions.delete_heading'))
            ->modalDescription(function (array $arguments): string {
                $material = $this->resolveMaterial($arguments, 'delete');

                return __('candidates.materials.actions.delete_description', [
                    'candidate' => $this->getCandidate()->name,
                    'file' => $material->original_name ?? __('candidates.materials.unnamed_file'),
                ]);
            })
            ->modalSubmitActionLabel(__('candidates.materials.actions.delete_confirm'))
            ->action(function (array $arguments, CandidateMaterialLifecycle $lifecycle): void {
                $material = $this->resolveMaterial($arguments, 'delete');
                $lifecycle->delete($this->getCompany(), $material, $this->getCurrentUser());
                $this->refreshCandidateRecord();

                Notification::make()->title(__('candidates.materials.notifications.deleted'))->success()->send();
            });
    }

    /** @param array<string, mixed> $arguments */
    private function resolveMaterial(array $arguments, string $ability): CandidateMaterial
    {
        $materialId = $arguments['material'] ?? null;

        abort_unless(is_numeric($materialId), 404);

        $candidate = $this->getCandidate();
        $material = CandidateMaterial::query()
            ->where('company_id', $candidate->company_id)
            ->where('candidate_id', $candidate->getKey())
            ->whereNull('deleted_at')
            ->findOrFail((int) $materialId);

        Gate::forUser($this->getCurrentUser())->authorize($ability, $material);

        return $material;
    }

    private function refreshCandidateRecord(): void
    {
        $id = $this->getCandidate()->getKey();
        $this->record = CandidateResource::getEloquentQuery()->findOrFail((int) $id);

        // The "content" schema (profile/communications/applications/materials
        // cards) is built once per request and cached the first time Livewire
        // hydrates the page's discovered schemas — before this action even
        // runs. Reassigning $this->record above does not invalidate that
        // cache, so without clearing it the page would keep rendering the
        // pre-mutation data until a full reload rebuilds it from scratch.
        $this->cacheSchema('content', null);
    }

    private function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function getCurrentUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function content(Schema $schema): Schema
    {
        $candidate = $this->getCandidate();

        return $schema->components([
            View::make('filament.resources.candidates.components.profile')
                ->viewData(['profile' => $this->profileData($candidate)])
                ->columnSpanFull(),
            View::make('filament.resources.candidates.components.communications')
                ->viewData($this->communicationsData($candidate))
                ->columnSpanFull(),
            View::make('filament.resources.candidates.components.applications')
                ->viewData(['applications' => $this->applicationsData($candidate)])
                ->columnSpanFull(),
            View::make('filament.resources.candidates.components.materials')
                ->viewData($this->materialsData($candidate))
                ->columnSpanFull(),
        ]);
    }

    /** @return array<string, mixed> */
    private function profileData(Candidate $candidate): array
    {
        return [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => PhoneCountry::formatInternational($candidate->phone),
            'created_at' => $candidate->created_at?->translatedFormat('M j, Y'),
            'socials' => $this->socialProfiles($candidate),
        ];
    }

    /** @return array<string, mixed> */
    private function communicationsData(Candidate $candidate): array
    {
        $candidate->loadMissing([
            'communicationThreads.job',
            'communicationThreads.messages.authorizedBy',
        ]);

        $contextJob = $this->communicationJob();
        $threads = $candidate->communicationThreads
            ->when($contextJob instanceof Job, fn (Collection $threads): Collection => $threads->where('job_id', $contextJob->getKey()))
            ->sortByDesc(fn (CandidateCommunicationThread $thread): int => $thread->messages->max('id') ?? 0)
            ->map(function (CandidateCommunicationThread $thread): array {
                return [
                    'job' => $thread->job?->name,
                    'job_id' => $thread->job_id,
                    'messages' => $thread->messages
                        ->sortByDesc('id')
                        ->map(fn (CandidateCommunicationMessage $message): array => [
                            'subject' => $message->authorized_subject ?? $message->draft_subject,
                            'body' => $message->authorized_body ?? $message->draft_body,
                            'status' => $message->status->value,
                            'status_label' => __('communications.statuses.'.$message->status->value),
                            'kind_label' => $this->communicationKindLabel($message),
                            'ai_assisted' => $message->kind?->isRecruiterAuthored() && $message->ai_assisted,
                            'authorized_by' => $message->kind?->isRecruiterAuthored()
                                ? $message->authorized_by_name ?? $message->authorizedBy?->name
                                : null,
                            'sender' => $message->sender_email,
                            'recipient' => $message->recipient_email,
                            'sent_at' => ($message->sent_at ?? $message->send_requested_at ?? $message->created_at)?->translatedFormat('M j, Y · H:i'),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $hasInFlightDelivery = $candidate->communicationThreads
            ->pluck('messages')
            ->flatten()
            ->contains(fn (CandidateCommunicationMessage $message): bool => in_array($message->status, [
                CandidateCommunicationMessageStatus::Queued,
                CandidateCommunicationMessageStatus::Sending,
            ], true));

        return [
            'threads' => $threads,
            'is_do_not_contact' => $candidate->isDoNotContact(),
            'has_valid_email' => is_string($candidate->email) && filter_var($candidate->email, FILTER_VALIDATE_EMAIL) !== false,
            'context_job' => $contextJob?->name,
            'has_in_flight_delivery' => $hasInFlightDelivery,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function applicationsData(Candidate $candidate): array
    {
        $candidate->loadMissing([
            'applications.job',
            'applications.status',
            'applications.interviews',
        ]);

        return $candidate->applications
            ->sortByDesc('created_at')
            ->map(function (Application $application): array {
                $nextInterview = $application->interviews
                    ->filter(fn (Interview $interview): bool => $interview->status !== InterviewStatus::Cancelled
                        && $interview->ends_at->isFuture())
                    ->sortBy('scheduled_at')
                    ->first();

                $daysInStage = $application->daysInCurrentStage();

                return [
                    'job' => $application->job->name,
                    'job_url' => JobResource::getUrl('view', ['record' => $application->job]),
                    'status' => $application->status->name,
                    'status_color' => $application->status->color,
                    'stage_role' => match (true) {
                        $application->status->is_hired => 'hired',
                        $application->status->is_terminal => 'closed',
                        $application->status->is_final_stage => 'final_stage',
                        default => null,
                    },
                    // Only a fit that still measures the job's confirmed criteria
                    // is shown; an evaluation the criteria have moved past is not
                    // this candidate's current match for that process.
                    'score' => $application->hasCurrentEvaluation() && $application->analysis_score !== null
                        ? (int) round((float) $application->analysis_score)
                        : null,
                    'applied_at' => $application->created_at->translatedFormat('M j, Y'),
                    // Where they are is only half the answer; how long they have
                    // been there is what tells the recruiter whether this process
                    // is moving. Terminal outcomes are not "waiting".
                    'stage_age' => $application->status->is_terminal
                        ? null
                        : trans_choice('attention.days', $daysInStage, ['count' => $daysInStage]),
                    'is_overdue' => $application->isOverdueInCurrentStage(),
                    'next_interview' => $nextInterview?->scheduled_at
                        ->setTimezone($nextInterview->timezone)
                        ->translatedFormat('M j, Y · H:i'),
                    'url' => ApplicationResource::getUrl('view', ['record' => $application]),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function materialsData(Candidate $candidate): array
    {
        $candidate->loadMissing([
            'materials.addedBy',
            'materials.batch',
            'importOrigin.addedBy',
            'applications.job',
        ]);

        $available = $candidate->materials
            ->filter(fn (CandidateMaterial $material): bool => $material->isAvailable())
            ->sortBy([['added_at', 'desc'], ['id', 'desc']])
            ->values();

        $archived = $candidate->materials
            ->reject(fn (CandidateMaterial $material): bool => $material->isAvailable())
            ->sortBy([['added_at', 'desc'], ['id', 'desc']])
            ->values();

        $company = Filament::getTenant();
        $company = $company instanceof Company ? $company : null;

        return [
            'importOrigin' => $this->importOriginData($candidate),
            'available' => $this->mapMaterials($available, $candidate, $company),
            'archived' => $this->mapMaterials($archived, $candidate, $company),
            'archivedCount' => $archived->count(),
            'applications' => $candidate->applications
                ->sortByDesc('created_at')
                ->map(fn (Application $application): array => [
                    'job' => $application->job->name,
                    'url' => ApplicationResource::getUrl('view', ['record' => $application]),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function importOriginData(Candidate $candidate): ?array
    {
        $origin = $candidate->importOrigin;

        if ($origin === null) {
            return null;
        }

        return [
            'source_label' => $origin->source_label,
            'added_at' => $origin->added_at->translatedFormat('M j, Y'),
            'added_by' => $origin->addedBy?->name,
            'has_batch' => $origin->batch_id !== null,
        ];
    }

    /**
     * @param  Collection<int, CandidateMaterial>  $materials
     * @return list<array<string, mixed>>
     */
    private function mapMaterials(Collection $materials, Candidate $candidate, ?Company $company): array
    {
        return array_values($materials
            ->map(function (CandidateMaterial $material) use ($candidate, $company): array {
                $status = $material->effectivePreparationStatus();

                return [
                    'id' => $material->getKey(),
                    'original_name' => $material->original_name,
                    'extension' => $material->extension,
                    'size' => $this->humanFileSize($material->size),
                    'is_available' => $material->isAvailable(),
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'can_retry' => in_array($status, [
                        CandidateMaterialPreparationStatus::Stored,
                        CandidateMaterialPreparationStatus::Failed,
                        CandidateMaterialPreparationStatus::NoReadableText,
                        CandidateMaterialPreparationStatus::FileUnavailable,
                    ], true),
                    'text_is_partial' => $material->text_is_partial,
                    'added_at' => $material->added_at->translatedFormat('M j, Y'),
                    'added_by' => $material->addedBy?->name,
                    'source_label' => $material->source_label,
                    'received_on' => $material->received_on?->translatedFormat('M j, Y'),
                    'has_batch' => $material->batch_id !== null,
                    'view_url' => $material->extension === 'pdf' && $company !== null
                        ? route('candidate-materials.view', ['company' => $company->slug, 'candidate' => $candidate, 'material' => $material])
                        : null,
                    'download_url' => $company !== null
                        ? route('candidate-materials.download', ['company' => $company->slug, 'candidate' => $candidate, 'material' => $material])
                        : null,
                ];
            })
            ->all());
    }

    private function statusLabel(CandidateMaterialPreparationStatus $status): string
    {
        return __('candidates.materials.statuses.'.$status->value);
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return round($value, 1).' '.$unit;
            }

            $value /= 1024;
        }

        return round($value, 1).' GB';
    }

    /** @return list<array{label: string, account: string, url: string|null}> */
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

    private function getCandidate(): Candidate
    {
        $record = $this->getRecord();

        if (! $record instanceof Candidate) {
            throw new LogicException('The candidate view page must be bound to a candidate.');
        }

        return $record;
    }

    private function communicationJob(): ?Job
    {
        $jobId = $this->communicationJobId;

        if ($jobId === null || $jobId < 1) {
            return null;
        }

        return Job::query()
            ->where('company_id', $this->getCandidate()->company_id)
            ->find($jobId);
    }

    private function communicationJobOrFail(): Job
    {
        $job = $this->communicationJob();

        abort_unless($job instanceof Job, 404);

        return $job;
    }

    private function communicationThread(): ?CandidateCommunicationThread
    {
        $job = $this->communicationJob();

        if (! $job instanceof Job) {
            return null;
        }

        return CandidateCommunicationThread::query()
            ->where('company_id', $this->getCandidate()->company_id)
            ->where('candidate_id', $this->getCandidate()->getKey())
            ->where('job_id', $job->getKey())
            ->with('messages')
            ->first();
    }

    private function candidateHasValidEmail(): bool
    {
        return is_string($this->getCandidate()->email)
            && filter_var($this->getCandidate()->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function defaultCommunicationLocale(): string
    {
        $locale = $this->getCurrentUser()->locale ?? app()->getLocale();

        return ApplicationLocale::tryFrom($locale)?->value ?? ApplicationLocale::English->value;
    }

    private function notifyCommunicationException(CandidateCommunicationException $exception): void
    {
        $settingsUrl = EmailProviderSettings::getUrl(tenant: $this->getCompany());
        $notification = Notification::make()
            ->title(__($this->communicationErrorKey($exception)))
            ->danger();

        if ($exception->getMessage() === CandidateCommunicationException::providerUnavailable()->getMessage()) {
            $notification->actions([
                Action::make('emailProviderSettings')
                    ->label(__('communications.actions.open_email_provider_settings'))
                    ->url($settingsUrl)
                    ->button(),
            ]);
        }

        $notification->send();
    }

    private function hasUsableEmailProvider(): bool
    {
        return $this->defaultCommunicationSender() !== null;
    }

    /** @return array<string, mixed> */
    private function composerFormState(): array
    {
        $draft = $this->communicationDraft();

        return [
            'draft_id' => $draft?->getKey(),
            'recipient' => $this->getCandidate()->email,
            'sender' => $this->defaultCommunicationSender(),
            'subject' => $draft?->draft_subject,
            'body' => $draft?->draft_body,
        ];
    }

    private function communicationDraft(): ?CandidateCommunicationMessage
    {
        return $this->communicationThread()?->messages()
            ->where('status', CandidateCommunicationMessageStatus::Draft)
            ->latest('id')
            ->first();
    }

    private function composerDescription(): string
    {
        $description = __('communications.composer.job_context', ['job' => $this->communicationJobOrFail()->name]);

        if (! $this->hasUsableEmailProvider()) {
            $description .= ' '.__('communications.composer.provider_unavailable');
        }

        return $description;
    }

    private function defaultCommunicationSender(): ?string
    {
        try {
            return app(CandidateCommunicationService::class)
                ->usableDefaultProvider($this->getCompany())
                ->validSenderAddress();
        } catch (CandidateCommunicationException) {
            return null;
        }
    }

    private function hasEligibleFollowUpContext(): bool
    {
        $thread = $this->communicationThread();

        return $thread?->messages()
            ->where('kind', CandidateCommunicationMessageKind::RecruiterAuthored)
            ->whereNotNull('authorized_at')
            ->whereNotNull('authorized_body')
            ->exists() ?? false;
    }

    private function communicationKindLabel(CandidateCommunicationMessage $message): string
    {
        return match ($message->kind) {
            CandidateCommunicationMessageKind::RecruiterAuthored => __('communications.history.recruiter_message'),
            CandidateCommunicationMessageKind::PipelineStatusNotification => __('communications.history.pipeline_status_notification'),
            CandidateCommunicationMessageKind::InterviewScheduled => __('communications.history.interview_scheduled_notification'),
            CandidateCommunicationMessageKind::InterviewRescheduled => __('communications.history.interview_rescheduled_notification'),
            CandidateCommunicationMessageKind::InterviewCancelled => __('communications.history.interview_cancelled_notification'),
            null => __('communications.history.recorded_message'),
        };
    }

    private function communicationErrorKey(CandidateCommunicationException $exception): string
    {
        return match ($exception->getMessage()) {
            CandidateCommunicationException::candidateHasNoValidEmail()->getMessage() => 'communications.errors.invalid_email',
            CandidateCommunicationException::candidateIsDoNotContact()->getMessage() => 'communications.errors.do_not_contact',
            CandidateCommunicationException::providerUnavailable()->getMessage() => 'communications.errors.provider_unavailable',
            CandidateCommunicationException::aiUnavailable()->getMessage() => 'communications.errors.ai_unavailable',
            CandidateCommunicationException::aiAllowanceReached()->getMessage() => 'communications.errors.ai_allowance_reached',
            CandidateCommunicationException::followUpRequiresPriorOutboundMessage()->getMessage() => 'communications.errors.follow_up_requires_history',
            CandidateCommunicationException::alreadyAuthorized()->getMessage() => 'communications.errors.already_authorized',
            CandidateCommunicationException::draftCannotBeEdited()->getMessage() => 'communications.errors.draft_cannot_be_edited',
            default => 'communications.errors.unavailable',
        };
    }
}
