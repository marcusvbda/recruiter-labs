<?php

namespace App\Data;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\Interview;
use App\Services\RecruitmentProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The recruiter's immediate commitments, shaped as an agenda: the interviews
 * they own, grouped by the day they happen on, read in one timezone.
 *
 * It is a preview of the calendar, never a second calendar. It adds no query of
 * its own — it shapes interviews already scoped by
 * {@see RecruitmentProgressService::upcomingInterviewsQuery()} —
 * and it keeps the number of commitments that did not fit, so the agenda never
 * pretends to be the whole week.
 *
 * Every row leads somewhere: an interview whose application is gone is dropped
 * rather than rendered as a dead end.
 */
class RecruiterAgendaPreview
{
    /**
     * @param  list<array{key: string, is_today: bool, is_tomorrow: bool, date_label: string, interviews: list<array<string, string|null>>}>  $days
     */
    public function __construct(
        public readonly array $days,
        public readonly string $timezone,
        public readonly int $total,
        public readonly int $listed,
    ) {}

    public static function empty(string $timezone): self
    {
        return new self([], $timezone, 0, 0);
    }

    /**
     * @param  Collection<int, Interview>  $interviews  Already ordered by when they happen.
     * @param  int  $total  Every commitment ahead, including the ones not passed in.
     */
    public static function forInterviews(Collection $interviews, string $timezone, int $total): self
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $tomorrow = $today->addDay();

        /** @var array<string, array{date: CarbonImmutable, interviews: list<array<string, string|null>>}> $days */
        $days = [];
        $listed = 0;

        foreach ($interviews as $interview) {
            $application = $interview->application;

            if (! $application instanceof Application) {
                continue;
            }

            $localTime = $interview->scheduled_at->setTimezone($timezone);
            $dayKey = $localTime->format('Y-m-d');

            $days[$dayKey] ??= ['date' => $localTime->startOfDay(), 'interviews' => []];
            $days[$dayKey]['interviews'][] = self::row($interview, $application, $localTime);
            $listed++;
        }

        return new self(
            array_values(array_map(
                fn (array $day): array => [
                    'key' => $day['date']->format('Y-m-d'),
                    'is_today' => $day['date']->equalTo($today),
                    'is_tomorrow' => $day['date']->equalTo($tomorrow),
                    'date_label' => $day['date']->translatedFormat('D, M j'),
                    'interviews' => $day['interviews'],
                ],
                $days,
            )),
            $timezone,
            max($total, $listed),
            $listed,
        );
    }

    public function isEmpty(): bool
    {
        return $this->listed === 0;
    }

    /** Commitments ahead that this preview does not list. */
    public function hiddenCount(): int
    {
        return max(0, $this->total - $this->listed);
    }

    /**
     * @return array<string, string|null>
     */
    private static function row(Interview $interview, Application $application, CarbonImmutable $localTime): array
    {
        $rsvp = $interview->rsvp_status;

        return [
            'time' => $localTime->format('H:i'),
            'datetime' => $localTime->toIso8601String(),
            'candidate' => $application->candidate?->name,
            'job' => $application->job?->name,
            'url' => ApplicationResource::getUrl('view', [
                'record' => $application,
                'section' => 'interviews',
            ]),
            'rsvp_label' => (string) __("applications.admin.interviews.rsvp.{$rsvp->value}"),
            // A declined invitation is the one RSVP that is a problem, so it is
            // the only one allowed to raise its voice here. Everything else stays
            // quiet: the attention queue is where broken commitments are chased.
            'rsvp_tone' => match ($rsvp->value) {
                'declined' => 'danger',
                'accepted' => 'success',
                default => 'neutral',
            },
        ];
    }
}
