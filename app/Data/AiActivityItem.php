<?php

namespace App\Data;

use Carbon\CarbonInterface;

/**
 * One line of AI work as a recruiter reads it.
 *
 * Derived, never persisted. It carries the recruiting role doing the work
 * ("Criteria analyst"), what that work is about in plain language, and a way
 * in when a meaningful destination exists. It deliberately has no progress
 * field: nothing here may imply a measurement the system cannot make.
 */
class AiActivityItem
{
    public function __construct(
        /** The recruiting job being performed, e.g. "Criteria analyst". */
        public readonly string $role,
        /** What is being done, e.g. "Evaluating Sofia Martins for Backend Engineer". */
        public readonly string $description,
        public readonly ?string $url = null,
        /** Why this is waiting or blocked, in recruiter language. Null when working or done. */
        public readonly ?string $reason = null,
        /** How many operations this single line stands for (>1 when aggregated). */
        public readonly int $count = 1,
        public readonly ?CarbonInterface $occurredAt = null,
    ) {}

    /** Same line, stamped with when the work actually finished. */
    public function withOccurredAt(?CarbonInterface $occurredAt): self
    {
        return new self(
            role: $this->role,
            description: $this->description,
            url: $this->url,
            reason: $this->reason,
            count: $this->count,
            occurredAt: $occurredAt,
        );
    }

    public function isAggregated(): bool
    {
        return $this->count > 1;
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'description' => $this->description,
            'url' => $this->url,
            'reason' => $this->reason,
            'count' => $this->count,
            'occurred_at' => $this->occurredAt?->toIso8601String(),
        ];
    }
}
