<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class InterviewEmailContext implements RecruitmentEmailContext
{
    public function __construct(
        public int $interviewId,
        public int $notificationSequence,
        public string $candidateName,
        public string $candidateEmail,
        public string $jobTitle,
        public string $employerName,
        public CarbonImmutable $scheduledAt,
        public string $timezone,
        public ?string $meetingUrl = null,
    ) {}

    public function recipientEmail(): string
    {
        return $this->candidateEmail;
    }

    public function companyName(): string
    {
        return $this->employerName;
    }

    public function idempotencyKey(): string
    {
        return 'interview:'.$this->interviewId.':'.$this->notificationSequence;
    }

    public function formattedDate(): string
    {
        return $this->scheduledAt->setTimezone($this->timezone)->format('F j, Y');
    }

    public function formattedTime(): string
    {
        return $this->scheduledAt->setTimezone($this->timezone)->format('g:i A T');
    }
}
