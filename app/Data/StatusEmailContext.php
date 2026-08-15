<?php

namespace App\Data;

/**
 * A candidate email produced by a pipeline status's on-enter communication.
 * The template is already resolved when the context is built, so the queued job
 * never has to reach back into the recruiter's configuration.
 */
readonly class StatusEmailContext implements RecruitmentEmailContext
{
    public function __construct(
        public int $applicationId,
        public int $statusId,
        public string $candidateEmail,
        public string $employerName,
        public string $subject,
        public string $body,
        public int $enteredAt,
    ) {}

    public function recipientEmail(): string
    {
        return $this->candidateEmail;
    }

    public function companyName(): string
    {
        return $this->employerName;
    }

    /**
     * Scoped to the moment of the transition: an application legitimately
     * re-entering a status later must email again, while a double-submitted
     * move within the same second must not.
     */
    public function idempotencyKey(): string
    {
        return "application:{$this->applicationId}:status:{$this->statusId}:{$this->enteredAt}";
    }
}
