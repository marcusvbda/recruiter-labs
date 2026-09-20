<?php

namespace App\Models;

use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\EmailProvider;
use App\Exceptions\CandidateCommunicationException;
use App\Services\RecruiterNotifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

/**
 * The final candidate-facing content is snapshotted at authorization time.
 * Delivery transitions may continue to update, but historical content and its
 * trusted recipient/sender/provider identity are never rewritten.
 *
 * @property CandidateCommunicationMessageStatus $status
 * @property CandidateCommunicationMessageKind|null $kind
 * @property bool $ai_assisted
 * @property Carbon|null $authorized_at
 */
#[Fillable(['company_id', 'thread_id', 'kind', 'draft_subject', 'draft_body', 'ai_assisted'])]
class CandidateCommunicationMessage extends Model
{
    /** @var list<string> */
    private const SNAPSHOT_ATTRIBUTES = [
        'kind',
        'ai_assisted',
        'authorized_by_id',
        'authorized_by_name',
        'provider_setting_id',
        'authorized_subject',
        'authorized_body',
        'recipient_email',
        'sender_email',
        'provider',
        'idempotency_key',
        'authorized_at',
    ];

    /** @var list<string> */
    private const SYSTEM_SNAPSHOT_ATTRIBUTES = [
        'kind',
        'ai_assisted',
        'authorized_by_id',
        'authorized_by_name',
        'provider_setting_id',
        'draft_subject',
        'draft_body',
        'authorized_subject',
        'authorized_body',
        'recipient_email',
        'sender_email',
        'provider',
        'idempotency_key',
        'send_requested_at',
        'authorized_at',
    ];

    /**
     * Set while a write that moves this message into a terminal delivery
     * failure is in flight, so the `saved` hook can tell a real transition
     * from a re-save. Never persisted.
     */
    protected bool $entersFailure = false;

    protected function casts(): array
    {
        return [
            'status' => CandidateCommunicationMessageStatus::class,
            'kind' => CandidateCommunicationMessageKind::class,
            'ai_assisted' => 'boolean',
            'provider' => EmailProvider::class,
            'authorized_at' => 'datetime',
            'send_requested_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CandidateCommunicationMessage $message): void {
            if ($message->getRawOriginal('authorized_at') !== null
                && $message->isDirty(self::SNAPSHOT_ATTRIBUTES)) {
                throw CandidateCommunicationException::alreadyAuthorized();
            }

            if ($message->getRawOriginal('kind') !== null
                && $message->getRawOriginal('kind') !== CandidateCommunicationMessageKind::RecruiterAuthored->value
                && $message->isDirty(self::SYSTEM_SNAPSHOT_ATTRIBUTES)) {
                throw CandidateCommunicationException::alreadyAuthorized();
            }
        });

        // Drives the candidate/application communications panel's realtime
        // refresh instead of polling.
        static::saved(function (CandidateCommunicationMessage $message): void {
            RealtimeEvent::dispatch('candidate_communications_'.$message->thread->candidate_id, 'CandidateCommunicationMessageUpdated');
        });

        // A message the workspace committed to sending and could not deliver
        // is the one communication ending worth interrupting a recruiter for.
        // This is the single terminal point every send path funnels through —
        // recruiter-authored messages and automatic stage emails alike — so
        // hooking it here keeps one notification per failure no matter which
        // path produced it. Re-saving an already-failed message is not a new
        // failure, hence the transition guard.
        static::saving(function (CandidateCommunicationMessage $message): void {
            // Read while the write is still pending: after the save, a model
            // that was saved without changing anything still reports the
            // *previous* save's changes, which would turn an idle re-save into
            // a second failure.
            $message->entersFailure = $message->isDirty('status')
                && $message->status === CandidateCommunicationMessageStatus::Failed;
        });

        static::saved(function (CandidateCommunicationMessage $message): void {
            if (! $message->entersFailure) {
                return;
            }

            $message->entersFailure = false;

            DB::afterCommit(function () use ($message): void {
                app(RecruiterNotifier::class)->candidateCommunicationFailed($message);
            });
        });
    }

    public function isAuthorized(): bool
    {
        return $this->authorized_at !== null;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CandidateCommunicationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CandidateCommunicationThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }

    /** @return BelongsTo<CompanyEmailProviderSetting, $this> */
    public function providerSetting(): BelongsTo
    {
        return $this->belongsTo(CompanyEmailProviderSetting::class, 'provider_setting_id');
    }

    /** @return BelongsTo<RecruitmentEmailDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(RecruitmentEmailDelivery::class, 'delivery_id');
    }
}
