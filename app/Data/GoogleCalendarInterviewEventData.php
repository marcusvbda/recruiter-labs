<?php

namespace App\Data;

use App\Enums\InterviewRsvpStatus;

readonly class GoogleCalendarInterviewEventData
{
    public function __construct(
        public string $eventId,
        public ?string $conferenceId,
        public ?string $meetingUrl,
        public InterviewRsvpStatus $rsvpStatus,
        public bool $isCancelled,
        public bool $conferenceCreationFailed,
    ) {}
}
