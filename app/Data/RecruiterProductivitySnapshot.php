<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * What Recruiter Labs actually finished for one workspace inside one recent
 * period, plus the capacity it has left to keep doing it.
 *
 * Every figure here is a count of completed, still-valid work. Nothing in this
 * object is a model statistic: there is no token count, no cost and no
 * provider detail — those belong to AI Settings.
 */
class RecruiterProductivitySnapshot
{
    public function __construct(
        public readonly CarbonImmutable $periodStart,
        public readonly CarbonImmutable $periodEnd,
        /** Applications whose current evaluation was produced automatically in the period. */
        public readonly int $applicationsEvaluated,
        /** Job criteria sets prepared automatically that are the job's current ones. */
        public readonly int $criteriaPrepared,
        /** Profiles an explicitly requested sourcing run read in the period. */
        public readonly int $sourcingProfilesAnalyzed,
        /** The workspace's manual-review baseline, in minutes, when it set one. */
        public readonly ?int $manualReviewMinutes,
        /** AI allowance for the current billing cycle. */
        public readonly UsageMetricData $aiAllowance,
    ) {}

    /**
     * Useful automatic AI work completed in the period: the evaluations plus
     * the criteria preparations behind them. Both are recruiter work that no
     * longer had to happen, which is what makes them comparable; failed,
     * superseded and user-requested runs are already excluded upstream.
     */
    public function aiWorkCompleted(): int
    {
        return $this->applicationsEvaluated + $this->criteriaPrepared;
    }

    public function hasSourcing(): bool
    {
        return $this->sourcingProfilesAnalyzed > 0;
    }

    /**
     * The estimate, in minutes, or null when the workspace has no baseline —
     * in which case the product shows measured counts and says nothing about
     * time at all.
     */
    public function estimatedMinutesSaved(): ?int
    {
        if ($this->manualReviewMinutes === null || $this->manualReviewMinutes < 1) {
            return null;
        }

        return $this->applicationsEvaluated * $this->manualReviewMinutes;
    }

    public function hasAnything(): bool
    {
        return $this->aiWorkCompleted() > 0 || $this->hasSourcing();
    }
}
