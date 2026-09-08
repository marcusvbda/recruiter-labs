<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * One previous hiring process a candidate took part in, summarised for the
 * recruiter to read beside a sourcing suggestion.
 *
 * This is contextual history for a human, never agent input. It carries the job,
 * when the person applied and how far the workflow took them — and deliberately
 * nothing else. There is no field for structured interview feedback and none for
 * a previous per-application AI evaluation (fit, coverage or confidence),
 * because a new suggestion must be assessed from the candidate's own material
 * against this job's confirmed criteria, not from what a different job's model
 * run once concluded.
 *
 * The type is intentionally unlike {@see CandidateSourcingMaterial}: nothing
 * here can be mistaken for something the sanitizer or the sourcing agent
 * accepts.
 */
final class CandidateRecruitmentHistoryEntry
{
    public function __construct(
        public readonly int $jobId,
        public readonly string $jobName,
        public readonly CarbonImmutable $appliedAt,
        /** The workflow stage the application sits in now, as the workspace named it. */
        public readonly string $statusName,
        /** The stage is one the workflow marks as close to a decision. */
        public readonly bool $reachedFinalStage = false,
        /** The definitive positive outcome, as configured on the stage. */
        public readonly bool $wasHired = false,
        /** The process ended — hired, rejected, withdrawn or disqualified. */
        public readonly bool $isClosed = false,
        /** The application belongs to the job the recruiter is sourcing for right now. */
        public readonly bool $isCurrentJob = false,
    ) {}
}
