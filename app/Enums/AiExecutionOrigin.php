<?php

namespace App\Enums;

/**
 * Describes what initiated an AI operation independently from the actor who
 * requested or caused it. A user can be recorded for an automatic operation
 * (for example, confirming criteria releases waiting evaluations) without that
 * making the operation user-requested.
 */
enum AiExecutionOrigin: string
{
    case Automatic = 'automatic';
    case UserRequested = 'user_requested';
}
