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
    /**
     * The record existed before execution provenance was captured. Its source
     * must not be inferred from the actor or operation that happened to be
     * stored alongside it.
     */
    case LegacyUnknown = 'legacy_unknown';

    case Automatic = 'automatic';
    case UserRequested = 'user_requested';
}
