<?php

namespace App\Data;

use App\Enums\AiActivityState;

/**
 * Everything the AI indicator and the AI Activity panel show for one
 * workspace at one moment, computed from real persisted operation state.
 *
 * Counts are the number of actual operations, not the number of displayed
 * lines: aggregation collapses lines, it never inflates or deflates the
 * underlying count the indicator reports.
 */
class AiActivitySnapshot
{
    public function __construct(
        public readonly AiActivityState $state,
        public readonly int $workingCount,
        public readonly int $waitingCount,
        public readonly int $blockedCount,
        /** @var list<AiActivityItem> */
        public readonly array $working = [],
        /** @var list<AiActivityItem> */
        public readonly array $waiting = [],
        /** @var list<AiActivityItem> */
        public readonly array $blocked = [],
        /** @var list<AiActivityItem> */
        public readonly array $recent = [],
    ) {}

    /** The number shown beside the indicator label, or null when it shows none. */
    public function indicatorCount(): ?int
    {
        return match ($this->state) {
            AiActivityState::Working => $this->workingCount,
            AiActivityState::Waiting => $this->waitingCount,
            default => null,
        };
    }

    public function hasAnything(): bool
    {
        return $this->workingCount > 0
            || $this->waitingCount > 0
            || $this->blockedCount > 0
            || $this->recent !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'state_label' => $this->state->label(),
            'state_color' => $this->state->color(),
            'state_icon' => $this->state->icon(),
            'indicator_count' => $this->indicatorCount(),
            'working_count' => $this->workingCount,
            'waiting_count' => $this->waitingCount,
            'blocked_count' => $this->blockedCount,
            'working' => array_map(fn (AiActivityItem $item): array => $item->toArray(), $this->working),
            'waiting' => array_map(fn (AiActivityItem $item): array => $item->toArray(), $this->waiting),
            'blocked' => array_map(fn (AiActivityItem $item): array => $item->toArray(), $this->blocked),
            'recent' => array_map(fn (AiActivityItem $item): array => $item->toArray(), $this->recent),
        ];
    }
}
