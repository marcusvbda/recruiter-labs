<?php

namespace App\Data;

use App\Enums\Limit;

class PlanLimitChangeData
{
    public function __construct(
        public readonly Limit $limit,
        public readonly ?int $from,
        public readonly ?int $to,
    ) {}

    /** @return array{key: string, from: int|null, to: int|null} */
    public function toArray(): array
    {
        return ['key' => $this->limit->value, 'from' => $this->from, 'to' => $this->to];
    }
}
