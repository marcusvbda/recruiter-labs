<?php

namespace App\Data;

use App\Services\WorkspaceActivationJourney;

/**
 * How far one workspace is along the activation journey, shaped for reading.
 *
 * It carries no logic of its own beyond arithmetic: what completes a step and
 * where a step leads is decided once, in
 * {@see WorkspaceActivationJourney}, so every onboarding surface shows the same
 * progress and the same next action.
 *
 * Optional setup is a separate list, never mixed into the primary steps. It is
 * excluded from the percentage and from both milestones on purpose: work the
 * product does not require must not be able to make itself look required.
 */
class WorkspaceActivationProgress
{
    /**
     * @param  list<array{key: string, is_complete: bool, url: string|null}>  $primarySteps  In the order the product presents them. `url` is where the workspace goes to complete the step, and is null once there is nothing left to do there.
     * @param  list<array{key: string, is_done: bool, is_actionable: bool, url: string}>  $optionalSteps  `is_actionable` is the existing authorization for the underlying product action, not an onboarding-specific rule.
     */
    public function __construct(
        public readonly array $primarySteps,
        public readonly array $optionalSteps,
        private readonly bool $setupComplete,
        private readonly bool $activated,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'primary_steps' => $this->primarySteps,
            'optional_steps' => $this->optionalSteps,
            'completed_count' => $this->completedCount(),
            'total_count' => $this->totalCount(),
            'percentage' => $this->percentage(),
            'next_step' => $this->nextStep(),
            'is_setup_complete' => $this->isSetupComplete(),
            'is_activated' => $this->isActivated(),
        ];
    }

    /**
     * The one useful thing to do next: the first primary step the workspace has
     * not reached yet. Null once the journey is over.
     *
     * @return array{key: string, is_complete: bool, url: string|null}|null
     */
    public function nextStep(): ?array
    {
        foreach ($this->primarySteps as $step) {
            if (! $step['is_complete']) {
                return $step;
            }
        }

        return null;
    }

    public function completedCount(): int
    {
        return count(array_filter($this->primarySteps, fn (array $step): bool => $step['is_complete']));
    }

    public function totalCount(): int
    {
        return count($this->primarySteps);
    }

    /** Progress over the primary steps only — optional setup never counts. */
    public function percentage(): int
    {
        if ($this->totalCount() === 0) {
            return 0;
        }

        return (int) round($this->completedCount() / $this->totalCount() * 100);
    }

    public function isSetupComplete(): bool
    {
        return $this->setupComplete;
    }

    public function isActivated(): bool
    {
        return $this->activated;
    }
}
