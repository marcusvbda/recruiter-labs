<?php

namespace App\Data;

use App\Models\Interview;
use Carbon\CarbonImmutable;

/**
 * A single entry rendered on the recruiter's weekly agenda, normalized from
 * either a local {@see Interview} or a Google Calendar event.
 */
readonly class CalendarAgendaEvent
{
    public function __construct(
        public string $key,
        public string $title,
        public ?string $subtitle,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $isAllDay,
        public bool $isInterview,
        public ?string $url = null,
        public ?string $badge = null,
    ) {}
}
