<?php

namespace App\Enums;

/**
 * How loudly an attention item asks to be dealt with. Three levels only: a
 * finer scale would invite arguing about ranking instead of doing the work.
 */
enum RecruitmentAttentionSeverity: string
{
    /** Something is broken or a commitment has fallen through. */
    case Critical = 'critical';

    /** Somebody is waiting on the recruiter. */
    case Warning = 'warning';

    /** Worth knowing before it becomes either of the above. */
    case Info = 'info';

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
        };
    }

    /** Lower sorts first. */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Warning => 1,
            self::Info => 2,
        };
    }
}
