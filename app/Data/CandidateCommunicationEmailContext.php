<?php

namespace App\Data;

/**
 * An immutable recruiter-authorized outbound message. Values originate in the
 * candidate communication snapshot, never from queued live candidate data.
 */
readonly class CandidateCommunicationEmailContext implements RecruitmentEmailContext
{
    public function __construct(
        public int $messageId,
        public string $recipient,
        public string $company,
        public string $subject,
        public string $body,
        public string $key,
    ) {}

    public function recipientEmail(): string
    {
        return $this->recipient;
    }

    public function companyName(): string
    {
        return $this->company;
    }

    public function idempotencyKey(): string
    {
        return $this->key;
    }
}
