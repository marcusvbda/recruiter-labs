<?php

namespace App\Events;

use App\Actions\MoveApplicationToStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An application arrived in a status — either by being created in the pipeline's
 * first status, or by being moved through {@see MoveApplicationToStatus}.
 *
 * This is intentionally the only workflow event: a status decides for itself
 * whether entering it communicates anything to the candidate.
 */
class ApplicationEnteredStatus implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $applicationId,
        public readonly int $statusId,
        public readonly ?int $previousStatusId,
    ) {}
}
