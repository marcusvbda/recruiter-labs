<?php

namespace Database\Factories;

use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewRsvpStatus;
use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = now()->addDays(3)->startOfHour();

        return [
            'application_id' => Application::factory(),
            // Resolved after `application_id`: a caller who forgets `->for()`
            // still gets a tenancy-consistent fixture, since the interview and
            // the person holding it belong to the application's own company.
            'company_id' => fn (array $attributes) => Application::query()
                ->whereKey($attributes['application_id'])
                ->firstOrFail()
                ->company_id,
            'calendar_user_id' => function (array $attributes) {
                $user = User::factory()->create();
                $user->companies()->attach($attributes['company_id']);

                return $user->id;
            },
            'calendar_integration_id' => null,
            'schedule_request_key' => null,
            // The default interview is still ahead: nothing an interview has
            // not run yet can have established, so it is deliberately not
            // eligible for feedback until a state says otherwise.
            'status' => InterviewStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addHour(),
            'timezone' => 'UTC',
            // Unique per row: the calendar event a real interview maps to.
            'calendar_event_id' => 'evt_'.$this->faker->unique()->uuid(),
            'calendar_conference_id' => null,
            'meeting_url' => null,
            'rsvp_status' => InterviewRsvpStatus::Accepted,
            'calendar_sync_status' => InterviewCalendarSyncStatus::Synced,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * An interview that has already taken place — the only shape that can
     * receive feedback ({@see Interview::canReceiveFeedback()}).
     */
    public function held(): static
    {
        return $this->state(function (): array {
            $scheduledAt = now()->subDays(2)->startOfHour();

            return [
                'scheduled_at' => $scheduledAt,
                'ends_at' => $scheduledAt->copy()->addHour(),
            ];
        });
    }

    /**
     * An interview that was called off. Combines with {@see held()} to describe
     * an interview that was cancelled and whose slot has since passed.
     */
    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => InterviewStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'The candidate withdrew.',
        ]);
    }
}
