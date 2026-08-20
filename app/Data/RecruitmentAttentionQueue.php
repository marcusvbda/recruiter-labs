<?php

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * What needs attention right now, ordered, plus how much was left out.
 *
 * A queue that renders two hundred rows is as unhelpful as one that renders
 * none, so each signal contributes a bounded number of items. `total` keeps the
 * difference visible instead of silently pretending the list is complete.
 */
class RecruitmentAttentionQueue
{
    /** @param Collection<int, RecruitmentAttentionItem> $items */
    public function __construct(
        public readonly Collection $items,
        public readonly int $total,
    ) {}

    public static function empty(): self
    {
        /** @var Collection<int, RecruitmentAttentionItem> $items */
        $items = new Collection;

        return new self($items, 0);
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    /** Items detected but not listed, because their signal hit its display cap. */
    public function hiddenCount(): int
    {
        return max(0, $this->total - $this->items->count());
    }

    /** @return list<array<string, int|string|null>> */
    public function toArray(): array
    {
        return array_values($this->items
            ->map(fn (RecruitmentAttentionItem $item): array => $item->toArray())
            ->all());
    }
}
