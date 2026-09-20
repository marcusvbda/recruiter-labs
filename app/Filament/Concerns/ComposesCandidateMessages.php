<?php

namespace App\Filament\Concerns;

use App\Data\EmailTemplateContext;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Exceptions\CandidateCommunicationException;
use App\Filament\Clusters\Settings\Pages\EmailProviderSettings;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\User;
use App\Services\CandidateCommunicationService;
use App\Services\EmailTemplateRenderer;
use App\Support\CandidateMessageBody;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * The one recruiter messaging composer: Send message → optionally choose a
 * template → edit → Send.
 *
 * It is a concern rather than two page implementations because an Application
 * and a Candidate profile differ only in how much context is already known: the
 * Application already knows its Job, the profile may have to be told. Everything
 * else — the recipient and sending identity shown before Send, template
 * resolution, the unresolved-context guard, and the single write path through
 * {@see CandidateCommunicationService} — is identical and must stay identical.
 *
 * There is deliberately no AI drafting and no message-purpose choice here.
 */
trait ComposesCandidateMessages
{
    /**
     * Template variables the composer's current context cannot fill. Kept on the
     * page (not only inside the schema) so the modal's Send button can be
     * disabled while a message still depends on context nobody supplied.
     *
     * @var list<string>
     */
    public array $unresolvedMessageTokens = [];

    abstract protected function messageCandidate(): Candidate;

    abstract protected function messageCompany(): Company;

    abstract protected function messageActor(): User;

    /**
     * The Job this composer is locked to, when the surface already knows it.
     * An Application's message belongs to that hiring process and must never
     * silently attach itself to another one.
     */
    protected function messageFixedJob(): ?Job
    {
        return null;
    }

    /** The Job pre-selected when the composer opens, when one is selectable. */
    protected function messageDefaultJobId(): ?int
    {
        return null;
    }

    /**
     * The Jobs this candidate may be messaged about.
     *
     * @return array<int, string>
     */
    protected function messageJobOptions(): array
    {
        return [];
    }

    /** Re-read whatever the page renders after a message changed. */
    protected function afterCandidateMessageSent(): void {}

    public function sendMessageAction(): Action
    {
        return Action::make('sendMessage')
            ->label(__('communications.actions.send_message'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalHeading(fn (): string => __('communications.composer.heading', [
                'candidate' => $this->messageCandidate()->name,
            ]))
            ->modalDescription(fn (): string => $this->messageComposerDescription())
            ->modalSubmitActionLabel(__('communications.actions.send'))
            ->modalSubmitAction(fn (Action $action): Action => $action->disabled(
                fn (): bool => ! $this->canSendCandidateMessage(),
            ))
            ->extraModalFooterActions(fn (Action $action): array => $this->messageComposerFooterActions($action))
            ->fillForm(fn (): array => $this->messageComposerState())
            ->schema([
                TextInput::make('recipient')
                    ->label(__('communications.fields.recipient'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sender')
                    ->label(__('communications.fields.sender'))
                    ->placeholder(__('communications.composer.sender_unavailable'))
                    ->disabled()
                    ->dehydrated(false),
                Hidden::make('draft_id'),
                Select::make('job_id')
                    ->label(__('communications.fields.job_context'))
                    ->placeholder(__('communications.composer.no_job_context'))
                    ->helperText(__('communications.composer.job_helper'))
                    ->options(fn (): array => $this->messageJobOptions())
                    ->visible(fn (): bool => $this->messageFixedJob() === null && $this->messageJobOptions() !== [])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        // A draft belongs to one candidate/job conversation, so
                        // changing the Job starts from this composer's content
                        // rather than editing the other conversation's draft.
                        $set('draft_id', null);

                        $this->applyMessageTemplate($get, $set);
                    }),
                Select::make('email_template_id')
                    ->label(__('communications.fields.template'))
                    ->placeholder(__('communications.composer.no_template'))
                    ->helperText(__('communications.composer.template_helper'))
                    ->options(fn (): array => $this->messageTemplateOptions())
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->applyMessageTemplate($get, $set)),
                TextInput::make('subject')
                    ->label(__('communications.fields.subject'))
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get) => $this->refreshUnresolvedMessageTokens($get)),
                RichEditor::make('body')
                    ->label(__('communications.fields.body'))
                    ->fileAttachments(false)
                    ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get) => $this->refreshUnresolvedMessageTokens($get)),
                // Never sent with a hole in it: the missing context is named,
                // and Send stays unavailable until it is supplied or removed.
                Placeholder::make('unresolved_context')
                    ->hiddenLabel()
                    ->visible(fn (): bool => $this->unresolvedMessageTokens !== [])
                    ->content(fn (): string => __('communications.composer.unresolved_context', [
                        'variables' => implode(', ', $this->unresolvedMessageTokens),
                    ])),
            ])
            ->action(function (array $data, array $arguments, CandidateCommunicationService $communications): void {
                if ($arguments['discardDraft'] ?? false) {
                    $this->discardComposerDraft($data, $communications);

                    return;
                }

                $this->sendComposedMessage($data, $communications);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendComposedMessage(array $data, CandidateCommunicationService $communications): void
    {
        $candidate = $this->messageCandidate();
        $job = $this->composerJob($data['job_id'] ?? null);
        $context = $this->messageContext($job);
        $renderer = $this->messageRenderer();

        $subjectInput = is_string($data['subject'] ?? null) ? $data['subject'] : '';
        $bodyInput = $this->messageBodyHtml(is_string($data['body'] ?? null) || is_array($data['body'] ?? null) ? $data['body'] : null);

        $unresolved = $this->unresolvedTokensFor($subjectInput, $bodyInput, $context);

        if ($unresolved !== []) {
            $this->unresolvedMessageTokens = $unresolved;

            Notification::make()
                ->title(__('communications.errors.unresolved_context', ['variables' => implode(', ', $unresolved)]))
                ->danger()
                ->send();

            throw new Halt;
        }

        // Any token still written by hand is resolved here too, so the sent
        // content can never contain raw template syntax.
        $subject = trim($renderer->render($subjectInput, $context));
        $body = $renderer->render($bodyInput, $context, escape: true);

        if ($subject === '' || trim(strip_tags($body)) === '') {
            Notification::make()->title(__('communications.errors.subject_and_body_required'))->danger()->send();

            throw new Halt;
        }

        try {
            $thread = $communications->resolveThread(
                $this->messageActor(),
                $this->messageCompany(),
                $candidate,
                $job,
                $this->messageApplicationFor($job),
            );

            $draft = $this->composerDraftFor($data['draft_id'] ?? null, $thread);

            $draft = $draft instanceof CandidateCommunicationMessage
                ? $communications->updateDraft($this->messageActor(), $draft, $subject, $body)
                : $communications->createDraft($this->messageActor(), $thread, $subject, $body);

            $communications->authorizeDraft($this->messageActor(), $draft);
        } catch (CandidateCommunicationException $exception) {
            $this->notifyCandidateCommunicationException($exception);

            return;
        }

        $this->afterCandidateMessageSent();

        Notification::make()->title(__('communications.notifications.send_requested'))->success()->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function discardComposerDraft(array $data, CandidateCommunicationService $communications): void
    {
        $draftId = $data['draft_id'] ?? null;
        $draft = is_numeric($draftId)
            ? CandidateCommunicationMessage::query()
                ->where('company_id', $this->messageCompany()->getKey())
                ->whereKey((int) $draftId)
                ->first()
            : null;

        if (! $draft instanceof CandidateCommunicationMessage) {
            Notification::make()->title(__('communications.errors.unavailable'))->danger()->send();

            return;
        }

        try {
            $communications->discardDraft($this->messageActor(), $draft);
        } catch (CandidateCommunicationException $exception) {
            $this->notifyCandidateCommunicationException($exception);

            return;
        }

        $this->afterCandidateMessageSent();

        Notification::make()->title(__('communications.notifications.draft_discarded'))->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageComposerState(): array
    {
        $this->unresolvedMessageTokens = [];

        $job = $this->composerJob($this->messageDefaultJobId());
        $draft = $this->composerLegacyDraft($job);

        // A draft written before this composer existed lives in one Job's
        // conversation; opening it there is what makes it recoverable.
        if ($job === null && $draft?->thread?->job instanceof Job) {
            $job = $draft->thread->job;
        }

        $state = [
            'recipient' => $this->messageCandidate()->email,
            'sender' => $this->defaultCandidateMessageSender(),
            'draft_id' => $draft?->getKey(),
            'job_id' => $job?->getKey(),
            'email_template_id' => null,
            'subject' => $draft instanceof CandidateCommunicationMessage ? $draft->draft_subject : '',
            // A legacy draft was plain text; it must open in the editor with
            // its line breaks and literal characters intact.
            'body' => $draft instanceof CandidateCommunicationMessage
                ? $this->messageBodyHtml($draft->draft_body)
                : '',
        ];

        // An unsent draft written before this composer existed is recoverable
        // content, not something to destroy: it opens as the working copy.
        $this->unresolvedMessageTokens = $this->unresolvedTokensFor(
            (string) $state['subject'],
            (string) $state['body'],
            $this->messageContext($job),
        );

        return $state;
    }

    private function applyMessageTemplate(Get $get, Set $set): void
    {
        $templateId = $get('email_template_id');
        $job = $this->composerJob($get('job_id'));
        $context = $this->messageContext($job);

        if (is_numeric($templateId)) {
            $template = EmailTemplate::query()
                ->where('company_id', $this->messageCompany()->getKey())
                ->available()
                ->whereKey((int) $templateId)
                ->first();

            if ($template instanceof EmailTemplate) {
                $renderer = $this->messageRenderer();

                $set('subject', $renderer->renderForComposer($template->subject, $context));
                $set('body', $renderer->renderForComposer($template->body, $context, escape: true));
            }
        }

        $this->refreshUnresolvedMessageTokens($get);
    }

    private function refreshUnresolvedMessageTokens(Get $get): void
    {
        $context = $this->messageContext($this->composerJob($get('job_id')));

        $this->unresolvedMessageTokens = $this->unresolvedTokensFor(
            is_string($subject = $get('subject')) ? $subject : '',
            is_string($body = $get('body')) ? $body : '',
            $context,
        );
    }

    /**
     * @return list<string>
     */
    private function unresolvedTokensFor(string $subject, string $body, EmailTemplateContext $context): array
    {
        $renderer = $this->messageRenderer();

        return array_values(array_unique(array_merge(
            $renderer->unresolvedTokens($subject, $context),
            $renderer->unresolvedTokens($body, $context),
        )));
    }

    private function messageContext(?Job $job): EmailTemplateContext
    {
        $candidate = $this->messageCandidate();
        $application = $this->messageApplicationFor($job);

        return $application instanceof Application
            ? EmailTemplateContext::forApplication($application)
            : EmailTemplateContext::forCandidate($candidate, $job, $this->messageCompany());
    }

    /**
     * The Application this message belongs to, when one already exists.
     * Messaging never creates one: a talent-pool candidate is simply messaged
     * without an application context.
     */
    private function messageApplicationFor(?Job $job): ?Application
    {
        if (! $job instanceof Job) {
            return null;
        }

        return Application::query()
            ->where('company_id', $this->messageCompany()->getKey())
            ->where('candidate_id', $this->messageCandidate()->getKey())
            ->where('job_id', $job->getKey())
            ->first();
    }

    private function composerJob(mixed $jobId): ?Job
    {
        $fixed = $this->messageFixedJob();

        if ($fixed instanceof Job) {
            return $fixed;
        }

        if (! is_numeric($jobId)) {
            return null;
        }

        return Job::query()
            ->where('company_id', $this->messageCompany()->getKey())
            ->find((int) $jobId);
    }

    /**
     * The unsent draft this composer opens with. When the page has no Job in
     * play the newest draft from any of this candidate's conversations is
     * offered: an existing draft is recoverable content, never something the
     * simpler composer silently drops.
     */
    private function composerLegacyDraft(?Job $job): ?CandidateCommunicationMessage
    {
        $threadIds = CandidateCommunicationThread::query()
            ->where('company_id', $this->messageCompany()->getKey())
            ->where('candidate_id', $this->messageCandidate()->getKey())
            ->when($job instanceof Job, fn ($query) => $query->where('job_id', $job?->getKey()))
            ->pluck('id');

        if ($threadIds->isEmpty()) {
            return null;
        }

        return CandidateCommunicationMessage::query()
            ->whereIn('thread_id', $threadIds)
            ->where('company_id', $this->messageCompany()->getKey())
            ->where('status', CandidateCommunicationMessageStatus::Draft)
            ->whereNull('authorized_at')
            ->with('thread.job')
            ->latest('id')
            ->first();
    }

    private function composerDraftFor(mixed $draftId, CandidateCommunicationThread $thread): ?CandidateCommunicationMessage
    {
        if (! is_numeric($draftId)) {
            return null;
        }

        return $thread->messages()
            ->where('status', CandidateCommunicationMessageStatus::Draft)
            ->whereNull('authorized_at')
            ->whereKey((int) $draftId)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function messageTemplateOptions(): array
    {
        /** @var array<int, string> $options */
        $options = EmailTemplate::query()
            ->where('company_id', $this->messageCompany()->getKey())
            ->available()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $options;
    }

    /**
     * @return list<Action>
     */
    private function messageComposerFooterActions(Action $action): array
    {
        $actions = [
            $action->makeModalSubmitAction('discardCommunicationDraft', arguments: ['discardDraft' => true])
                ->label(__('communications.actions.discard_draft'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->composerLegacyDraft(
                    $this->composerJob($this->messageDefaultJobId()),
                ) instanceof CandidateCommunicationMessage),
        ];

        if (! $this->hasUsableCandidateMessageProvider()) {
            $actions[] = Action::make('configureEmailProvider')
                ->label(__('communications.actions.open_email_provider_settings'))
                ->url(EmailProviderSettings::getUrl(tenant: $this->messageCompany()))
                ->color('gray');
        }

        return $actions;
    }

    private function messageComposerDescription(): string
    {
        $job = $this->messageFixedJob();

        $description = $job instanceof Job
            ? __('communications.composer.job_context', ['job' => $job->name])
            : __('communications.composer.description');

        if (! $this->hasUsableCandidateMessageProvider()) {
            $description .= ' '.__('communications.composer.provider_unavailable');
        }

        return $description;
    }

    private function canSendCandidateMessage(): bool
    {
        return ! $this->messageCandidate()->isDoNotContact()
            && $this->candidateMessageRecipientIsValid()
            && $this->hasUsableCandidateMessageProvider()
            && $this->unresolvedMessageTokens === [];
    }

    private function candidateMessageRecipientIsValid(): bool
    {
        $email = $this->messageCandidate()->email;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function hasUsableCandidateMessageProvider(): bool
    {
        return $this->defaultCandidateMessageSender() !== null;
    }

    /**
     * The workspace's configured sending identity. The recruiter sees it and
     * never chooses it: transport stays a workspace setting.
     */
    private function defaultCandidateMessageSender(): ?string
    {
        try {
            return app(CandidateCommunicationService::class)
                ->usableDefaultProvider($this->messageCompany())
                ->validSenderAddress();
        } catch (CandidateCommunicationException) {
            return null;
        }
    }

    /**
     * The rich-text editor works on a document structure while it is open; what
     * gets authorized and sent is always the sanitized HTML of what the
     * recruiter was looking at — the composer state is client-controlled and is
     * never trusted as markup.
     *
     * @param  string|array<mixed>|null  $body
     */
    private function messageBodyHtml(string|array|null $body): string
    {
        return CandidateMessageBody::toStoredHtml($body);
    }

    private function messageRenderer(): EmailTemplateRenderer
    {
        return app(EmailTemplateRenderer::class);
    }

    private function notifyCandidateCommunicationException(CandidateCommunicationException $exception): void
    {
        $notification = Notification::make()
            ->title(__($this->candidateCommunicationErrorKey($exception)))
            ->danger();

        if ($exception->getMessage() === CandidateCommunicationException::providerUnavailable()->getMessage()) {
            $notification->actions([
                Action::make('emailProviderSettings')
                    ->label(__('communications.actions.open_email_provider_settings'))
                    ->url(EmailProviderSettings::getUrl(tenant: $this->messageCompany()))
                    ->button(),
            ]);
        }

        $notification->send();
    }

    private function candidateCommunicationErrorKey(CandidateCommunicationException $exception): string
    {
        return match ($exception->getMessage()) {
            CandidateCommunicationException::candidateHasNoValidEmail()->getMessage() => 'communications.errors.invalid_email',
            CandidateCommunicationException::candidateIsDoNotContact()->getMessage() => 'communications.errors.do_not_contact',
            CandidateCommunicationException::providerUnavailable()->getMessage() => 'communications.errors.provider_unavailable',
            CandidateCommunicationException::alreadyAuthorized()->getMessage() => 'communications.errors.already_authorized',
            CandidateCommunicationException::draftCannotBeEdited()->getMessage() => 'communications.errors.draft_cannot_be_edited',
            default => 'communications.errors.unavailable',
        };
    }
}
