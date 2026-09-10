<?php

namespace App\Enums;

/**
 * What execution did with the document one row referenced.
 *
 * The document axis of a row's result. It exists on its own because "the
 * candidate was already here" and "the CV was already here" are different
 * statements, and a recruiter reading a report needs to know which of the two
 * is why nothing changed.
 */
enum CandidateImportMaterialOutcome: string
{
    /** The row never promised a document, or was contact-only. */
    case None = 'none';

    /** A new CV is now retained against the candidate. */
    case Retained = 'retained';

    /**
     * The candidate already held a document with exactly these contents, so
     * nothing was written. Not an error, and not a new version either.
     */
    case DuplicateSkipped = 'duplicate_skipped';

    /** A document was promised and could not be retained; the row failed with it. */
    case Failed = 'failed';
}
