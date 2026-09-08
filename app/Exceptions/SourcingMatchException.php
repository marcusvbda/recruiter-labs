<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Invalid recruiter decisions on a sourcing match.
 *
 * A match's state is a record of a human decision, so these are always the
 * result of an invalid request — a stale list, a double click, a candidate who
 * entered the job in the meantime — rather than a fault, and are not reported.
 */
class SourcingMatchException extends RuntimeException implements ShouldntReport
{
    public static function dismissedMustBeRestoredFirst(): self
    {
        return new self(__('sourcing.errors.dismissed_must_be_restored_first'));
    }

    public static function notDismissed(): self
    {
        return new self(__('sourcing.errors.not_dismissed'));
    }

    public static function candidateAlreadyInJob(): self
    {
        return new self(__('sourcing.errors.candidate_already_in_job'));
    }
}
