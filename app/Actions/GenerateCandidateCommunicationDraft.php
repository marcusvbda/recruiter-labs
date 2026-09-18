<?php

namespace App\Actions;

use App\Ai\Agents\DraftCandidateCommunication;
use App\Data\AiProviderConfigurationData;
use App\Enums\AiExecutionOrigin;
use App\Enums\ApplicationLocale;
use App\Enums\CandidateCommunicationDraftPurpose;
use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\Limit;
use App\Exceptions\CandidateCommunicationException;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\Company;
use App\Models\User;
use App\Services\AiCredentialsResolver;
use App\Services\AiUsageTracker;
use App\Services\CandidateCommunicationContextSanitizer;
use App\Services\CandidateCommunicationService;
use App\Services\CandidateSourcingContextSanitizer;
use App\Services\CandidateSourcingEligibilityService;
use App\Services\LimitManager;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

/**
 * The UI-facing, user-requested AI drafting boundary. It deliberately returns
 * a saved draft only after a valid structured response exists; a quota or
 * provider failure leaves any manual draft entirely untouched.
 */
class GenerateCandidateCommunicationDraft
{
    public const OPERATION = 'candidate_communication_draft';

    public const PROVIDER = 'openai';

    public function __construct(
        private readonly CandidateCommunicationService $communications,
        private readonly CandidateSourcingEligibilityService $candidateMaterials,
        private readonly CandidateSourcingContextSanitizer $candidateContext,
        private readonly CandidateCommunicationContextSanitizer $outboundContext,
        private readonly AiCredentialsResolver $credentialsResolver,
        private readonly AiUsageTracker $usageTracker,
        private readonly LimitManager $limitManager,
    ) {}

    public function handle(
        User $actor,
        CandidateCommunicationThread $thread,
        ApplicationLocale|string $language,
        CandidateCommunicationDraftPurpose $purpose = CandidateCommunicationDraftPurpose::InitialOutreach,
    ): CandidateCommunicationMessage {
        $language = $this->resolveLanguage($language);
        [$thread, $candidate, $job] = $this->communications->draftingContext($actor, $thread);

        if ($candidate->isDoNotContact()) {
            throw CandidateCommunicationException::candidateIsDoNotContact();
        }

        $priorOutboundMessages = $purpose === CandidateCommunicationDraftPurpose::FollowUp
            ? $this->priorOutboundMessages($thread, $candidate)
            : [];

        if ($purpose === CandidateCommunicationDraftPurpose::FollowUp && $priorOutboundMessages === []) {
            throw CandidateCommunicationException::followUpRequiresPriorOutboundMessage();
        }

        $configuration = $this->credentialsResolver->resolve($job->company);
        $this->assertAiIsAvailable($configuration, $job->company);

        $agent = new DraftCandidateCommunication($job);
        $materials = $this->candidateContext->sanitize(
            $candidate,
            $this->candidateMaterials->materialsFor($candidate),
        );
        $context = $agent->context($materials, $language, $purpose, $priorOutboundMessages);
        $model = $configuration->usesOwnKey ? $configuration->model : DraftCandidateCommunication::MODEL;
        $runtimeProvider = $this->credentialsResolver->registerRuntimeProvider($job->company, $configuration);
        $startedAt = hrtime(true);
        $usage = $this->usageTracker->startForJob(
            $job,
            $actor->getKey(),
            (string) Str::uuid(),
            self::OPERATION,
            self::PROVIDER,
            $model,
            $configuration->usesOwnKey,
            AiExecutionOrigin::UserRequested,
            $purpose === CandidateCommunicationDraftPurpose::FollowUp
                ? 'candidate_communication_follow_up_requested'
                : 'candidate_communication_outreach_requested',
        );

        try {
            $response = $agent->prompt(
                $context,
                provider: $runtimeProvider,
                model: $configuration->usesOwnKey ? $configuration->model : null,
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The candidate communication agent did not return structured output.');
            }

            $draft = $response->toArray();
            $subject = $draft['subject'] ?? null;
            $body = $draft['body'] ?? null;

            if (! is_string($subject) || ! is_string($body) || blank($subject) || blank($body)) {
                throw new UnexpectedValueException('The candidate communication agent response did not contain a usable draft.');
            }

            $this->usageTracker->complete($usage, $response->usage, $this->elapsedMilliseconds($startedAt));

            // This rechecks the actor, company, candidate and DNC state after
            // the remote call. It creates/updates AI drafts only; manual text
            // and authorization/delivery snapshots are never candidates here.
            return $this->communications->saveAiDraft($actor, $thread, $subject, $body);
        } catch (Throwable $exception) {
            $this->usageTracker->fail($usage, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        } finally {
            if ($runtimeProvider !== null) {
                $this->credentialsResolver->forgetRuntimeProvider($job->company);
            }
        }
    }

    /** @return list<string> */
    private function priorOutboundMessages(CandidateCommunicationThread $thread, Candidate $candidate): array
    {
        return $thread->messages()
            ->where('kind', CandidateCommunicationMessageKind::RecruiterAuthored)
            ->whereNotNull('authorized_at')
            ->whereNotNull('authorized_body')
            ->latest('authorized_at')
            ->latest('id')
            ->limit(3)
            ->get()
            ->reverse()
            ->map(fn (CandidateCommunicationMessage $message): ?string => $this->outboundContext->sanitizeOutboundText(
                $candidate,
                $message->authorized_body,
            ))
            ->filter()
            ->values()
            ->all();
    }

    private function assertAiIsAvailable(AiProviderConfigurationData $configuration, Company $company): void
    {
        if (! $configuration->isConfigured) {
            throw CandidateCommunicationException::aiUnavailable();
        }

        if (! $configuration->usesOwnKey && $this->limitManager->usage($company, Limit::AiAnalyses)->isReached) {
            throw CandidateCommunicationException::aiAllowanceReached();
        }
    }

    private function resolveLanguage(ApplicationLocale|string $language): ApplicationLocale
    {
        if ($language instanceof ApplicationLocale) {
            return $language;
        }

        return ApplicationLocale::tryFrom($language)
            ?? throw CandidateCommunicationException::unsupportedDraftLanguage();
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
