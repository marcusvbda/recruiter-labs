<?php

namespace App\Services;

use App\Enums\SourcingSearchStatus;
use App\Filament\Clusters\Settings\Pages\AiSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateImportBatch;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\SourcingSearch;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * The single place a recruiter-facing database notification is built.
 *
 * Notifications here are deliberately scarce. They exist for one reason only:
 * a background operation the recruiter started, or a delivery/commitment the
 * workspace made on their behalf, reached an ending they would otherwise have
 * to sit and watch for. They are *not* a second work queue — everything that
 * needs a decision keeps living in Attention — and they never carry a
 * candidate's fit, score or ranking, because a notification is about an
 * operation, never about how good someone is.
 *
 * Two rules hold for every method below:
 *
 * - **Recipients never leave the workspace.** The initiator is notified when
 *   the workspace still knows who they were and they may still enter; otherwise
 *   the workspace members who may enter are notified. Nobody outside the
 *   company is ever addressed, and a removed or disabled member is not either.
 * - **Sending happens after commit.** Callers wrap these in
 *   {@see DB::afterCommit()} (or are already outside a transaction), so work
 *   that is rolled back never announces itself.
 */
class RecruiterNotifier
{
    /**
     * Kinds are written into the stored payload so a notification can be found
     * again — deduplication and tests both key on this rather than on
     * translated copy.
     */
    public const KindImportCompleted = 'candidate_import_completed';

    public const KindImportFailed = 'candidate_import_failed';

    public const KindSourcingCompleted = 'sourcing_completed';

    public const KindSourcingFailed = 'sourcing_failed';

    public const KindSourcingBlocked = 'sourcing_blocked';

    public const KindCommunicationFailed = 'candidate_communication_failed';

    public const KindInterviewDeclined = 'interview_declined';

    public const KindCriteriaFailed = 'criteria_preparation_failed';

    public const KindAiAllowanceBlocked = 'ai_allowance_blocked';

    /**
     * An import that finished with something worth opening: candidates in the
     * pool, or rows waiting for a correction. An import that produced neither
     * is a non-event and stays silent.
     */
    public function candidateImportCompleted(CandidateImportBatch $batch, int $imported, int $unresolved): void
    {
        if ($imported < 1 && $unresolved < 1) {
            return;
        }

        $company = $batch->company;

        if (! $company instanceof Company) {
            return;
        }

        $this->deliver(
            $company,
            $batch->uploaded_by_id ?? $batch->confirmed_by_id,
            self::KindImportCompleted,
            __('notifications.candidate_import.completed.title'),
            __('notifications.candidate_import.completed.body', [
                'source' => $batch->source_label,
                'imported' => $imported,
                'unresolved' => $unresolved,
            ]),
            'info',
            __('notifications.candidate_import.completed.action'),
            $this->importUrl($batch, $company),
        );
    }

    public function candidateImportFailed(CandidateImportBatch $batch): void
    {
        $company = $batch->company;

        if (! $company instanceof Company) {
            return;
        }

        $this->deliver(
            $company,
            $batch->uploaded_by_id ?? $batch->confirmed_by_id,
            self::KindImportFailed,
            __('notifications.candidate_import.failed.title'),
            __('notifications.candidate_import.failed.body', ['source' => $batch->source_label]),
            'danger',
            __('notifications.candidate_import.failed.action'),
            $this->importUrl($batch, $company),
        );
    }

    /**
     * Only a sweep the recruiter asked for reaches this method — callers gate
     * on the execution origin, because a refresh nobody requested ending is
     * not news.
     */
    public function sourcingFinished(SourcingSearch $search, SourcingSearchStatus $status): void
    {
        $job = $search->job;
        $company = $search->company;

        if (! $job instanceof Job || ! $company instanceof Company) {
            return;
        }

        [$kind, $color] = match ($status) {
            SourcingSearchStatus::Completed => [self::KindSourcingCompleted, 'success'],
            SourcingSearchStatus::Failed => [self::KindSourcingFailed, 'danger'],
            SourcingSearchStatus::PendingQuota => [self::KindSourcingBlocked, 'warning'],
            default => [null, null],
        };

        if ($kind === null || $color === null) {
            return;
        }

        $key = match ($kind) {
            self::KindSourcingCompleted => 'completed',
            self::KindSourcingFailed => 'failed',
            default => 'blocked',
        };

        $this->deliver(
            $company,
            $search->requested_by_id,
            $kind,
            __("notifications.sourcing.{$key}.title", ['job' => $job->name]),
            __("notifications.sourcing.{$key}.body", [
                'job' => $job->name,
                // Counts describe work done, never who ranked where.
                'reviewed' => (int) $search->candidates_considered,
            ]),
            $color,
            __("notifications.sourcing.{$key}.action"),
            JobResource::getUrl('view', ['record' => $job, 'section' => 'sourcing'], tenant: $company),
        );
    }

    /**
     * A message the workspace committed to sending did not reach the
     * candidate. This says the delivery failed and where to look — it says
     * nothing about the candidate.
     */
    public function candidateCommunicationFailed(CandidateCommunicationMessage $message): void
    {
        $thread = $message->thread;

        if ($thread === null) {
            return;
        }

        $company = $thread->company;
        $candidate = $thread->candidate;

        if (! $company instanceof Company || $candidate === null) {
            return;
        }

        $this->deliver(
            $company,
            $message->authorized_by_id,
            self::KindCommunicationFailed,
            __('notifications.communication.failed.title', ['candidate' => $candidate->name]),
            __('notifications.communication.failed.body'),
            'danger',
            __('notifications.communication.failed.action'),
            CandidateResource::getUrl(
                'view',
                array_filter(['record' => $candidate, 'communicationJob' => $thread->job_id]),
                tenant: $company,
            ),
        );
    }

    public function interviewDeclined(Interview $interview): void
    {
        $company = $interview->company;
        $application = $interview->application;

        if (! $company instanceof Company || $application === null) {
            return;
        }

        $candidate = $application->candidate;

        $this->deliver(
            $company,
            $interview->calendar_user_id,
            self::KindInterviewDeclined,
            __('notifications.interview.declined.title', ['candidate' => $candidate->name]),
            __('notifications.interview.declined.body'),
            'warning',
            __('notifications.interview.declined.action'),
            ApplicationResource::getUrl(
                'view',
                ['record' => $application, 'section' => 'interviews'],
                tenant: $company,
            ),
        );
    }

    /**
     * Criteria preparation is the one AI step whose failure blocks everything
     * downstream for that job, so it is worth interrupting for. Per-candidate
     * evaluation failures deliberately have no equivalent here: at pool scale
     * they would be noise, and Attention already holds them.
     */
    public function criteriaPreparationFailed(Job $job): void
    {
        $company = $job->company;

        if (! $company instanceof Company) {
            return;
        }

        $this->deliver(
            $company,
            null,
            self::KindCriteriaFailed,
            __('notifications.criteria.failed.title', ['job' => $job->name]),
            __('notifications.criteria.failed.body'),
            'danger',
            __('notifications.criteria.failed.action'),
            JobResource::getUrl('edit', ['record' => $job], tenant: $company),
        );
    }

    /**
     * The workspace ran out of AI allowance and automatic work stopped.
     *
     * Dedupe rule: one notification per workspace while the situation is still
     * unacknowledged. If any member still holds an unread allowance-blocked
     * notification, this is the same situation they already know about and
     * nothing is sent. Reading it marks the situation as acknowledged, so the
     * next time work is blocked the workspace is told again.
     */
    public function aiAllowanceBlocked(Company $company): void
    {
        $recipients = $this->recipients($company, null);

        if ($recipients->isEmpty()) {
            return;
        }

        $alreadyPending = DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', $recipients->modelKeys())
            ->whereNull('read_at')
            ->where('data->viewData->kind', self::KindAiAllowanceBlocked)
            ->exists();

        if ($alreadyPending) {
            return;
        }

        $this->send(
            $recipients,
            self::KindAiAllowanceBlocked,
            __('notifications.ai_allowance.blocked.title'),
            __('notifications.ai_allowance.blocked.body'),
            'warning',
            __('notifications.ai_allowance.blocked.action'),
            AiSettings::getUrl(tenant: $company),
        );
    }

    private function importUrl(CandidateImportBatch $batch, Company $company): string
    {
        return CandidateImportBatchResource::getUrl('progress', ['record' => $batch], tenant: $company);
    }

    private function deliver(
        Company $company,
        ?int $initiatorId,
        string $kind,
        string $title,
        string $body,
        string $color,
        string $actionLabel,
        string $url,
    ): void {
        $recipients = $this->recipients($company, $initiatorId);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->send($recipients, $kind, $title, $body, $color, $actionLabel, $url);
    }

    /**
     * @param  EloquentCollection<int, User>  $recipients
     */
    private function send(
        EloquentCollection $recipients,
        string $kind,
        string $title,
        string $body,
        string $color,
        string $actionLabel,
        string $url,
    ): void {
        Notification::make()
            ->title($title)
            ->body($body)
            ->color($color)
            ->viewData(['kind' => $kind])
            ->actions([
                Action::make('open')
                    ->label($actionLabel)
                    ->url($url)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients, isEventDispatched: true);
    }

    /**
     * The initiator when the workspace still knows them and they may still
     * enter; otherwise everyone who may enter. Both branches read from the
     * company's own membership, which is what keeps this tenant-safe.
     *
     * @return EloquentCollection<int, User>
     */
    private function recipients(Company $company, ?int $initiatorId): EloquentCollection
    {
        /** @var EloquentCollection<int, User> $members */
        $members = $company->membersWithWorkspaceAccess()->get();

        if ($initiatorId !== null) {
            $initiator = $members->firstWhere(fn (User $user): bool => (int) $user->getKey() === $initiatorId);

            if ($initiator instanceof User) {
                /** @var EloquentCollection<int, User> $only */
                $only = new EloquentCollection([$initiator]);

                return $only;
            }
        }

        return $members;
    }
}
