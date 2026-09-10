<?php

namespace App\Enums;

/**
 * The only choices a reviewer may make about an imported row.
 *
 * There are five and there will not be a sixth: the product deliberately has no
 * spreadsheet editor, because a preview that let the recruiter retype a
 * candidate's email would be importing data the source file never contained,
 * and nothing downstream could tell the two apart. Everything a reviewer can do
 * is either "yes, this one", "no, not this one", or "yes, but without the
 * document" — a choice between things the file already said.
 *
 * A decision resolves the issues it was made to answer, and only those. Making
 * a row contact-only says nothing about the name on the candidate it reuses,
 * and confirming a reuse says nothing about which of three rows claiming that
 * candidate is the one to keep.
 */
enum CandidateImportDecisionKind: string
{
    /** Keep an already eligible row in the import. */
    case Include = 'include';

    /** Leave this row out of the import entirely. */
    case Exclude = 'exclude';

    /** Reuse the existing candidate shown, despite the name on file differing. */
    case ConfirmReuse = 'confirm_reuse';

    /** Import the contact and deliberately omit the CV this row referenced. */
    case ContactOnly = 'contact_only';

    /** Keep this row as the one representative of its intra-file duplicate group. */
    case ChooseDuplicate = 'choose_duplicate';

    /** Does this choice keep the row in the import at all? */
    public function selects(): bool
    {
        return $this !== self::Exclude;
    }

    /** Does the reviewer stop asking about this problem once the choice is made? */
    public function resolves(CandidateImportIssueCode $code): bool
    {
        return match ($this) {
            self::Exclude => true,
            self::Include => false,
            self::ConfirmReuse => $code === CandidateImportIssueCode::ExistingNameDiffers,
            self::ContactOnly => $code->concernsCv() || $code === CandidateImportIssueCode::ExistingMaterialsAtCapacity,
            self::ChooseDuplicate => $code === CandidateImportIssueCode::DuplicateInFile,
        };
    }
}
