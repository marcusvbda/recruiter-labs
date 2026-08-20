<?php

namespace App\Data;

use App\Enums\RecruitmentAttentionSeverity;
use App\Enums\RecruitmentAttentionType;

/**
 * One thing that currently needs a recruiter's attention.
 *
 * Derived, never persisted: an item exists for exactly as long as the state that
 * produced it. It carries its own explanation and its own way in, because an
 * attention item a recruiter cannot act on is just decoration.
 */
class RecruitmentAttentionItem
{
    public function __construct(
        public readonly RecruitmentAttentionType $type,
        public readonly string $title,
        /** Why this is being raised, in the recruiter's own terms. */
        public readonly string $explanation,
        public readonly string $actionLabel,
        public readonly string $actionUrl,
        /** Which hiring process this belongs to, for grouping and context. */
        public readonly ?string $context = null,
        public readonly ?int $jobId = null,
        public readonly ?int $applicationId = null,
        public readonly ?int $interviewId = null,
    ) {}

    public function severity(): RecruitmentAttentionSeverity
    {
        return $this->type->severity();
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'severity' => $this->severity()->value,
            'severity_color' => $this->severity()->color(),
            'icon' => $this->type->icon(),
            'title' => $this->title,
            'explanation' => $this->explanation,
            'action_label' => $this->actionLabel,
            'action_url' => $this->actionUrl,
            'context' => $this->context,
            'job_id' => $this->jobId,
            'application_id' => $this->applicationId,
            'interview_id' => $this->interviewId,
        ];
    }
}
