<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The staged upload behind a promised CV is no longer on disk.
 *
 * Raised instead of importing the contact alone, so the row fails whole and
 * the recruiter is told the document is gone rather than discovering later
 * that a candidate they believed they imported with a CV has none.
 */
class CandidateImportStagedFileMissing extends RuntimeException
{
    public function __construct(public readonly int $fileId)
    {
        parent::__construct('The staged CV for this row is no longer available.');
    }
}
