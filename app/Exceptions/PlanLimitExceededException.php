<?php

namespace App\Exceptions;

use App\Data\UsageMetricData;
use App\Enums\Limit;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class PlanLimitExceededException extends Exception implements ShouldntReport
{
    public function __construct(private readonly UsageMetricData $usageMetric)
    {
        parent::__construct(__('settings.errors.plan_limit_reached', [
            'limit' => $usageMetric->limit->label(),
        ]));
    }

    public function errorCode(): string
    {
        return 'plan_limit_exceeded.'.$this->usageMetric->limit->value;
    }

    public function limit(): Limit
    {
        return $this->usageMetric->limit;
    }

    public function metric(): UsageMetricData
    {
        return $this->usageMetric;
    }
}
