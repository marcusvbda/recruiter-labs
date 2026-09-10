<?php

namespace App\Enums;

use App\Services\CandidateMaterialErasure;

/**
 * Why a row was not applied, or why a whole execution stopped.
 *
 * Codes rather than sentences, for the same reason the issue codes are: the
 * same fact is rendered on the progress screen, in the final report and in the
 * correction file, and each of those phrases it differently. A code also
 * survives being stored on a row for a week without freezing a translation.
 *
 * `ResultErased` is the one value execution never writes: it is written by
 * {@see CandidateMaterialErasure} when the candidate or the CV a
 * row produced is deleted afterwards. Execution only reads it, and reads it as
 * "this row already happened and its result is gone" — never as an invitation
 * to produce it again.
 */
enum CandidateImportFailureCode: string
{
    /** The row's manifest is missing, so there is nothing execution may apply. */
    case ManifestMissing = 'manifest_missing';

    /** The candidate the review matched no longer exists in the workspace. */
    case IdentityMissing = 'identity_missing';

    /** That candidate still exists, but is no longer the person the row described. */
    case IdentityChanged = 'identity_changed';

    /** The row was going to create someone, and that email now belongs to an existing candidate. */
    case IdentityTaken = 'identity_taken';

    /** The staged upload behind the promised CV is gone, so the promise cannot be kept. */
    case MaterialUnavailable = 'material_unavailable';

    /** The candidate is at the retained-CV ceiling, so the document was refused. */
    case MaterialRejected = 'material_rejected';

    /** The row's own work threw, and its transaction was rolled back whole. */
    case RowFailed = 'row_failed';

    /** The importer lost workspace access, or the workspace lost the Candidates feature. */
    case AccessLost = 'access_lost';

    /** The execution itself stopped on a problem that was not any single row's. */
    case ExecutionFailed = 'execution_failed';

    /**
     * The worker holding this import stopped reporting progress and was reclaimed.
     *
     * Distinct from {@see self::ExecutionFailed} because nothing was refused:
     * the process simply went away — a killed worker, a deploy, a lost
     * database connection — and the batch is intact and resumable. It is a
     * separate code so the screen can offer "resume" as the obvious next move
     * instead of asking the recruiter to interpret a failure.
     */
    case ExecutionStalled = 'execution_stalled';

    /** Written by erasure, read by execution: the row's result no longer exists. */
    case ResultErased = 'result_erased';
}
