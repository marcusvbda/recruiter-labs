<?php

namespace App\Enums;

/**
 * The single aggregate state the persistent AI indicator shows.
 *
 * Every case is derived from real persisted operation state. There is no
 * "starting", "almost done" or percentage case on purpose: the product may
 * never display progress it cannot actually measure, and it may never show
 * work as running when no row is in a running state.
 */
enum AiActivityState: string
{
    /** Something needs a human before the AI work can complete. */
    case Blocked = 'blocked';

    /** At least one operation is queued or running right now. */
    case Working = 'working';

    /** Nothing is running, but something is waiting on a prerequisite. */
    case Waiting = 'waiting';

    /** Nothing is queued, running, waiting or failed. */
    case UpToDate = 'up_to_date';

    public function label(): string
    {
        return (string) __('ai_activity.state.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Blocked => 'danger',
            self::Working => 'info',
            self::Waiting => 'warning',
            self::UpToDate => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Blocked => 'heroicon-m-exclamation-triangle',
            self::Working => 'heroicon-m-sparkles',
            self::Waiting => 'heroicon-m-clock',
            self::UpToDate => 'heroicon-m-check-circle',
        };
    }

    /** Whether the indicator shows a count next to the label. */
    public function showsCount(): bool
    {
        return $this === self::Working || $this === self::Waiting;
    }
}
