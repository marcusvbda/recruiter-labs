<?php

namespace App\Enums;

/**
 * Why an import action was refused outright, as a code.
 *
 * These are not row issues: a row issue describes one record and leaves the
 * rest of the batch working, while a refusal means the action itself did not
 * happen. They are codes rather than sentences for the same reason issues are —
 * the same refusal is raised in a request, in a queue worker and in a report.
 */
enum CandidateImportRefusalCode: string
{
    /** The batch's stored preview no longer matches its inputs; it must be validated again. */
    case NotReviewable = 'not_reviewable';

    /** The revision the caller reviewed is not the revision the batch is on. */
    case StaleRevision = 'stale_revision';

    /** The batch was already confirmed; its manifest is fixed. */
    case AlreadyConfirmed = 'already_confirmed';

    /** The batch was discarded, expired, or is already executing. */
    case NotOpen = 'not_open';

    /** The file itself could not be read, so no part of it may be confirmed. */
    case StructuralErrors = 'structural_errors';

    /** The importer did not state that the candidates supplied their own data. */
    case DeclarationMissing = 'declaration_missing';

    /** Nothing is selected, so there is nothing to import. */
    case NothingSelected = 'nothing_selected';

    /** The batch holds more rows than one import may carry. */
    case RowLimitExceeded = 'row_limit_exceeded';

    /** The workspace already holds the maximum number of unconfirmed imports. */
    case UnconfirmedBatchLimit = 'unconfirmed_batch_limit';

    /** Another import of this workspace is already running; this one stays ready for review. */
    case ExecutionInProgress = 'execution_in_progress';

    /** No row in this batch carries that record number. */
    case RowNotFound = 'row_not_found';

    /** The row still carries problems that including it would silently ignore. */
    case RowNotEligible = 'row_not_eligible';

    /** There is no single existing candidate for this row to reuse. */
    case ReuseUnavailable = 'reuse_unavailable';

    /** The candidate the caller confirmed is not the one the row was shown. */
    case ReuseMismatch = 'reuse_mismatch';

    /** The row references no CV, so omitting one decides nothing. */
    case ContactOnlyNotApplicable = 'contact_only_not_applicable';

    /** The row belongs to no intra-file duplicate group. */
    case DuplicateGroupMissing = 'duplicate_group_missing';

    /** The batch cannot be discarded in its current state. */
    case NotDiscardable = 'not_discardable';
}
