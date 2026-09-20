<?php

namespace App\Services;

use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Exceptions\CandidateCommunicationException;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The only write boundary for recruiter-initiated candidate communications.
 *
 * Its arguments are deliberately Eloquent records rather than untrusted ids:
 * every call re-reads the records within the selected workspace before it
 * creates a thread, draft, or immutable authorization snapshot.
 */
class CandidateCommunicationService
{
    public function __construct(
        private readonly RecruitmentEmailSenderRegistry $senders,
        private readonly RecruitmentEmailDispatcher $emails,
    ) {}

    /**
     * The conversation with a candidate, optionally about one Job.
     *
     * The Job is optional because a talent-pool candidate with no Application
     * can still be messaged; that conversation is person-scoped and must not
     * fabricate an Application to exist. Every tenancy check is unchanged.
     */
    public function resolveThread(
        User $actor,
        Company $company,
        Candidate $candidate,
        ?Job $job = null,
        ?Application $application = null,
    ): CandidateCommunicationThread {
        Gate::forUser($actor)->authorize('update', $company);

        return DB::transaction(function () use ($company, $candidate, $job, $application): CandidateCommunicationThread {
            $company = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            $candidate = Candidate::query()
                ->whereBelongsTo($company)
                ->lockForUpdate()
                ->findOrFail($candidate->getKey());
            $job = $job === null
                ? null
                : Job::query()->whereBelongsTo($company)->lockForUpdate()->findOrFail($job->getKey());

            if ($job === null) {
                $application = null;
            } else {
                $application ??= Application::query()
                    ->whereBelongsTo($company)
                    ->whereBelongsTo($candidate)
                    ->whereBelongsTo($job)
                    ->lockForUpdate()
                    ->first();
            }

            if ($application !== null && (
                $application->company_id !== $company->getKey()
                || $application->candidate_id !== $candidate->getKey()
                || $application->job_id !== $job?->getKey()
            )) {
                throw CandidateCommunicationException::crossTenantContext();
            }

            /** @var CandidateCommunicationThread $thread */
            $thread = CandidateCommunicationThread::query()->firstOrCreate([
                'company_id' => $company->getKey(),
                'candidate_id' => $candidate->getKey(),
                'job_id' => $job?->getKey(),
            ]);

            // A sourced candidate can be contacted before applying. Once a
            // matching application exists, only this contextual pointer changes;
            // messages retain their original authorization snapshots.
            if ($application !== null && $thread->application_id !== $application->getKey()) {
                $thread->update(['application_id' => $application->getKey()]);
            }

            return $thread;
        });
    }

    public function createDraft(
        User $actor,
        CandidateCommunicationThread $thread,
        string $subject = '',
        string $body = '',
        bool $aiAssisted = false,
    ): CandidateCommunicationMessage {
        return DB::transaction(function () use ($actor, $thread, $subject, $body, $aiAssisted): CandidateCommunicationMessage {
            $thread = $this->lockedThreadForActor($actor, $thread);

            // Do-not-contact deliberately remains narrow: historical records and
            // manual drafts can still be read or prepared, but AI must not be a
            // route around the outreach restriction.
            if ($aiAssisted) {
                $candidate = Candidate::query()
                    ->whereBelongsTo($thread->company)
                    ->lockForUpdate()
                    ->findOrFail($thread->candidate_id);

                if ($candidate->isDoNotContact()) {
                    throw CandidateCommunicationException::candidateIsDoNotContact();
                }
            }

            return $thread->messages()->create([
                'company_id' => $thread->company_id,
                'kind' => CandidateCommunicationMessageKind::RecruiterAuthored,
                'draft_subject' => $subject,
                'draft_body' => $body,
                'ai_assisted' => $aiAssisted,
            ]);
        });
    }

    /**
     * Update the recruiter's working copy only. AI provenance belongs to the
     * original draft and is intentionally not caller-controlled here.
     */
    public function updateDraft(
        User $actor,
        CandidateCommunicationMessage $message,
        string $subject,
        string $body,
    ): CandidateCommunicationMessage {
        return DB::transaction(function () use ($actor, $message, $subject, $body): CandidateCommunicationMessage {
            $message = CandidateCommunicationMessage::query()
                ->whereKey($message->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $thread = $this->lockedThreadForActor(
                $actor,
                CandidateCommunicationThread::query()->findOrFail($message->thread_id),
            );

            if ($message->company_id !== $thread->company_id) {
                throw CandidateCommunicationException::crossTenantContext();
            }

            if ($message->isAuthorized()) {
                throw CandidateCommunicationException::alreadyAuthorized();
            }

            if ($message->status !== CandidateCommunicationMessageStatus::Draft) {
                throw CandidateCommunicationException::draftCannotBeEdited();
            }

            $message->update([
                'draft_subject' => $subject,
                'draft_body' => $body,
            ]);

            return $message;
        });
    }

    /**
     * Discard only a working copy. Authorised snapshots and delivery records are
     * intentionally never deletable through the recruiter composer boundary.
     */
    public function discardDraft(User $actor, CandidateCommunicationMessage $message): void
    {
        DB::transaction(function () use ($actor, $message): void {
            $message = CandidateCommunicationMessage::query()
                ->whereKey($message->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $thread = $this->lockedThreadForActor(
                $actor,
                CandidateCommunicationThread::query()->findOrFail($message->thread_id),
            );

            if ($message->company_id !== $thread->company_id) {
                throw CandidateCommunicationException::crossTenantContext();
            }

            if ($message->isAuthorized()) {
                throw CandidateCommunicationException::alreadyAuthorized();
            }

            if ($message->status !== CandidateCommunicationMessageStatus::Draft) {
                throw CandidateCommunicationException::draftCannotBeEdited();
            }

            $message->delete();
        });
    }

    /**
     * Copy the recruiter's final draft into immutable, trusted send fields.
     * Provider availability is intentionally checked here, rather than while a
     * draft is created, so unavailable settings never destroy a useful draft.
     */
    public function authorizeDraft(User $actor, CandidateCommunicationMessage $message): CandidateCommunicationMessage
    {
        $message = DB::transaction(function () use ($actor, $message): CandidateCommunicationMessage {
            $message = CandidateCommunicationMessage::query()
                ->whereKey($message->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $thread = $this->lockedThreadForActor(
                $actor,
                CandidateCommunicationThread::query()->findOrFail($message->thread_id),
            );

            if ($message->company_id !== $thread->company_id || $message->isAuthorized()) {
                throw $message->isAuthorized()
                    ? CandidateCommunicationException::alreadyAuthorized()
                    : CandidateCommunicationException::crossTenantContext();
            }

            $candidate = Candidate::query()
                ->whereBelongsTo($thread->company)
                ->lockForUpdate()
                ->findOrFail($thread->candidate_id);
            $this->assertCandidateCanReceiveOutreach($candidate);

            $providerSetting = $this->usableDefaultProvider($thread->company);

            $message->forceFill([
                'kind' => CandidateCommunicationMessageKind::RecruiterAuthored,
                'authorized_by_id' => $actor->getKey(),
                'authorized_by_name' => $actor->name,
                'provider_setting_id' => $providerSetting->getKey(),
                'status' => CandidateCommunicationMessageStatus::Queued,
                'authorized_subject' => $message->draft_subject,
                'authorized_body' => $message->draft_body,
                'recipient_email' => $candidate->email,
                'sender_email' => $providerSetting->validSenderAddress(),
                'provider' => $providerSetting->provider,
                'idempotency_key' => 'candidate-communication/'.Str::uuid(),
                'authorized_at' => now(),
                'send_requested_at' => now(),
            ])->save();

            return $message;
        });

        $this->emails->dispatchCandidateCommunication($message);

        return $message;
    }

    public function assertCandidateCanReceiveOutreach(Candidate $candidate): void
    {
        if ($candidate->isDoNotContact()) {
            throw CandidateCommunicationException::candidateIsDoNotContact();
        }

        if (! is_string($candidate->email) || filter_var($candidate->email, FILTER_VALIDATE_EMAIL) === false) {
            throw CandidateCommunicationException::candidateHasNoValidEmail();
        }
    }

    public function usableDefaultProvider(Company $company): CompanyEmailProviderSetting
    {
        $providerSetting = CompanyEmailProviderSetting::query()
            ->whereBelongsTo($company)
            ->where('is_default', true)
            ->first();

        if (! $providerSetting instanceof CompanyEmailProviderSetting
            || ! $this->senders->sender($providerSetting->provider)->isReady($providerSetting)) {
            throw CandidateCommunicationException::providerUnavailable();
        }

        return $providerSetting;
    }

    private function lockedThreadForActor(User $actor, CandidateCommunicationThread $thread): CandidateCommunicationThread
    {
        $thread = CandidateCommunicationThread::query()
            ->whereKey($thread->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $company = Company::query()->lockForUpdate()->findOrFail($thread->company_id);

        Gate::forUser($actor)->authorize('update', $company);

        $candidate = Candidate::query()->whereBelongsTo($company)->whereKey($thread->candidate_id)->first();
        $job = $thread->job_id === null
            ? null
            : Job::query()->whereBelongsTo($company)->whereKey($thread->job_id)->first();
        $application = $thread->application_id === null
            ? null
            : Application::query()->whereBelongsTo($company)->whereKey($thread->application_id)->first();

        if (! $candidate instanceof Candidate
            || ($thread->job_id !== null && ! $job instanceof Job)
            || ($thread->application_id !== null && (! $application instanceof Application
                || $application->candidate_id !== $candidate->getKey()
                || $application->job_id !== $thread->job_id))) {
            throw CandidateCommunicationException::crossTenantContext();
        }

        return $thread;
    }
}
