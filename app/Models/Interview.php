<?php

namespace App\Models;

use App\Enums\EmailNotificationType;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewRsvpStatus;
use App\Enums\InterviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $company_id
 * @property int $application_id
 * @property int $calendar_user_id
 * @property int|null $calendar_integration_id
 * @property string|null $schedule_request_key
 * @property InterviewStatus $status
 * @property CarbonImmutable $scheduled_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property string $calendar_event_id
 * @property string|null $calendar_conference_id
 * @property string|null $meeting_url
 * @property InterviewRsvpStatus $rsvp_status
 * @property CarbonImmutable|null $rsvp_responded_at
 * @property int $notification_sequence
 * @property EmailNotificationType|null $pending_notification_type
 * @property InterviewCalendarSyncStatus $calendar_sync_status
 * @property bool $calendar_sync_terminal
 * @property string|null $calendar_sync_error
 * @property CarbonImmutable|null $last_calendar_synced_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 */
#[Fillable(['company_id', 'application_id', 'calendar_user_id', 'calendar_integration_id', 'schedule_request_key', 'status', 'scheduled_at', 'ends_at', 'timezone', 'calendar_event_id', 'calendar_conference_id', 'meeting_url', 'rsvp_status', 'rsvp_responded_at', 'notification_sequence', 'pending_notification_type', 'calendar_sync_status', 'calendar_sync_terminal', 'calendar_sync_error', 'last_calendar_synced_at', 'cancelled_at', 'cancellation_reason'])]
class Interview extends Model
{
    protected $attributes = [
        'status' => InterviewStatus::Pending->value,
        'rsvp_status' => InterviewRsvpStatus::NeedsAction->value,
        'notification_sequence' => 0,
        'calendar_sync_terminal' => false,
        'calendar_sync_status' => InterviewCalendarSyncStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => InterviewStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'rsvp_status' => InterviewRsvpStatus::class,
            'rsvp_responded_at' => 'immutable_datetime',
            'notification_sequence' => 'integer',
            'pending_notification_type' => EmailNotificationType::class,
            'calendar_sync_status' => InterviewCalendarSyncStatus::class,
            'calendar_sync_terminal' => 'boolean',
            'last_calendar_synced_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Interviews that still stand: a cancelled one is no longer a commitment.
     *
     * @param  Builder<Interview>  $query
     * @return Builder<Interview>
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', InterviewStatus::Cancelled->value);
    }

    /**
     * A commitment that has not been kept yet: upcoming, or running right now.
     * An interview that already ended is history, not a pending commitment.
     *
     * @param  Builder<Interview>  $query
     * @return Builder<Interview>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->notCancelled()->where('ends_at', '>=', CarbonImmutable::now());
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function calendarUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calendar_user_id');
    }

    /** @return BelongsTo<ConnectedIntegration, $this> */
    public function calendarIntegration(): BelongsTo
    {
        return $this->belongsTo(ConnectedIntegration::class, 'calendar_integration_id');
    }
}
