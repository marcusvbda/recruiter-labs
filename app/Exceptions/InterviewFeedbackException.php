<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Domain rule violations when recording structured interview feedback. These are
 * always the result of an invalid request rather than a fault, so they are not
 * reported.
 */
class InterviewFeedbackException extends RuntimeException implements ShouldntReport
{
    /**
     * A cancelled interview never took place, so it cannot produce
     * completed-interview evidence about the candidate.
     */
    public static function interviewCancelled(): self
    {
        return new self(__('applications.errors.interview_feedback.interview_cancelled'));
    }

    /**
     * Feedback records what an interview established. Allowing it before the
     * interview ends would turn it into a speculative pre-interview judgement.
     */
    public static function interviewNotHeldYet(): self
    {
        return new self(__('applications.errors.interview_feedback.interview_not_held_yet'));
    }

    /**
     * A criterion from another job or another workspace fails the whole
     * submission: evidence from one hiring process must never silently become
     * evidence for another.
     */
    public static function criterionOutsideInterviewedJob(): self
    {
        return new self(__('applications.errors.interview_feedback.criterion_outside_interviewed_job'));
    }

    /**
     * A submission with no criterion results asserts nothing, and would leave an
     * attributable record behind that says nothing about the candidate.
     */
    public static function noCriteriaSubmitted(): self
    {
        return new self(__('applications.errors.interview_feedback.no_criteria_submitted'));
    }

    /**
     * One criterion, one result. Merging duplicates would let the last row
     * silently overwrite a contradictory answer the interviewer also gave.
     */
    public static function duplicateCriterion(): self
    {
        return new self(__('applications.errors.interview_feedback.duplicate_criterion'));
    }
}
