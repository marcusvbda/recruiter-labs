<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Domain rule violations of the pipeline/status workflow. These are always the
 * result of an invalid request rather than a fault, so they are not reported.
 */
class RecruitmentWorkflowException extends RuntimeException implements ShouldntReport
{
    public static function pipelineInUse(int $jobCount): self
    {
        return new self(__('pipelines.errors.pipeline_in_use', ['count' => $jobCount]));
    }

    public static function statusInUse(int $applicationCount): self
    {
        return new self(__('pipelines.errors.status_in_use', ['count' => $applicationCount]));
    }

    public static function pipelineLocked(): self
    {
        return new self(__('pipelines.errors.pipeline_locked'));
    }

    public static function crossTenantStatus(): self
    {
        return new self(__('pipelines.errors.cross_tenant_status'));
    }

    public static function crossPipelineStatus(): self
    {
        return new self(__('pipelines.errors.cross_pipeline_status'));
    }

    public static function missingInitialStatus(): self
    {
        return new self(__('pipelines.errors.missing_initial_status'));
    }
}
