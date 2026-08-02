<?php

namespace App\Exceptions;

use App\Enums\Feature;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class PlanFeatureUnavailableException extends Exception implements ShouldntReport
{
    public function __construct(private readonly Feature $feature)
    {
        parent::__construct(__('settings.errors.plan_feature_unavailable', [
            'feature' => $feature->label(),
        ]));
    }

    public function errorCode(): string
    {
        return 'plan_feature_unavailable.'.$this->feature->value;
    }

    public function feature(): Feature
    {
        return $this->feature;
    }
}
