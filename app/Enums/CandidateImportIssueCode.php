<?php

namespace App\Enums;

/**
 * Why one imported row is not simply importable as it stands.
 *
 * These are codes, not sentences. Validation runs in a queue worker as often as
 * in a request, and the same row is shown in the preview table, in an exported
 * correction file and in a recovery report, so the wording belongs to whichever
 * interface reports it. The extra facts a message needs — the limit, the
 * offending reason, the conflicting name — travel in the issue's context.
 *
 * Nothing here is repaired automatically. A code that {@see severity()} calls
 * `Decision` means the row is understood and waits for the reviewer to choose;
 * the choice itself is not made here.
 */
enum CandidateImportIssueCode: string
{
    /** `name` is blank after removing outer whitespace. */
    case NameMissing = 'name_missing';

    /** `name` is longer than 255 characters. */
    case NameTooLong = 'name_too_long';

    /** `email` is blank after removing outer whitespace. */
    case EmailMissing = 'email_missing';

    /** `email` is not one syntactically valid address. */
    case EmailInvalid = 'email_invalid';

    /** `email` is longer than 255 characters. */
    case EmailTooLong = 'email_too_long';

    /** `phone` is not an international number with `+` and 7-15 digits. */
    case PhoneInvalid = 'phone_invalid';

    /** `linkedin_url` is not an absolute HTTPS linkedin.com `/in/` profile URL. */
    case LinkedinUrlInvalid = 'linkedin_url_invalid';

    /** `linkedin_url` is longer than 255 characters. */
    case LinkedinUrlTooLong = 'linkedin_url_too_long';

    /** `cv_filename` is not a bare filename with an extension. */
    case CvFilenameInvalid = 'cv_filename_invalid';

    /** `cv_filename` is longer than 255 characters. */
    case CvFilenameTooLong = 'cv_filename_too_long';

    /** `received_on` is not a real `YYYY-MM-DD` calendar date. */
    case ReceivedOnInvalid = 'received_on_invalid';

    /** `received_on` is later than today in the workspace's timezone. */
    case ReceivedOnInFuture = 'received_on_in_future';

    /** `received_on` was supplied without a usable `cv_filename`. */
    case ReceivedOnWithoutCv = 'received_on_without_cv';

    /** The row's own `source_label` is longer than 120 characters. */
    case SourceLabelTooLong = 'source_label_too_long';

    /** The batch has no usable source label, so no row can inherit one. */
    case BatchSourceLabelMissing = 'batch_source_label_missing';

    /** More than one candidate in the workspace carries this email. */
    case EmailAmbiguous = 'email_ambiguous';

    /** Other rows in the same file carry this email; exactly one may be kept. */
    case DuplicateInFile = 'duplicate_in_file';

    /** The matched candidate's name differs from the one supplied. */
    case ExistingNameDiffers = 'existing_name_differs';

    /** The matched candidate already holds the retained-material limit. */
    case ExistingMaterialsAtCapacity = 'existing_materials_at_capacity';

    /** Reuse keeps the stored contact fields; the supplied ones are shown only. */
    case ExistingContactPreserved = 'existing_contact_preserved';

    /** No uploaded file carries the exact filename this row asks for. */
    case CvFileMissing = 'cv_file_missing';

    /** More than one uploaded file carries the filename this row asks for. */
    case CvFileAmbiguous = 'cv_file_ambiguous';

    /** More than one row asks for the same uploaded file. */
    case CvFileSharedByRows = 'cv_file_shared_by_rows';

    /** The very same document was supplied for rows claiming different identities. */
    case CvFileContentsRepeated = 'cv_file_contents_repeated';

    /** The uploaded file this row asks for was refused; `reason` carries the file's own code. */
    case CvFileRejected = 'cv_file_rejected';

    /** The upload's name is not a bare filename, or holds paths or control characters. */
    case CvFileNameInvalid = 'cv_file_name_invalid';

    /** The upload carries no bytes. */
    case CvFileEmpty = 'cv_file_empty';

    /** The upload is larger than a single CV may be. */
    case CvFileTooLarge = 'cv_file_too_large';

    /** The upload is not a PDF or a DOCX. */
    case CvFileTypeUnsupported = 'cv_file_type_unsupported';

    /** The upload is corrupt, encrypted, or its contents disagree with its extension. */
    case CvFileUnreadable = 'cv_file_unreadable';

    /** The upload could not be retained on the private disk. */
    case CvFileStorageFailed = 'cv_file_storage_failed';

    /** Uploads no row asks for; they are counted and reported, never attached. */
    case CvFilesUnreferenced = 'cv_files_unreferenced';

    /** The reviewer chose to import this contact without the CV the row referenced. */
    case CvOmittedByReviewer = 'cv_omitted_by_reviewer';

    /** Another row of this duplicate group was chosen, so this one is left out. */
    case DuplicateNotChosen = 'duplicate_not_chosen';

    /** The reviewer left this row out of the import. */
    case ExcludedByReviewer = 'excluded_by_reviewer';

    public function severity(): CandidateImportIssueSeverity
    {
        return match ($this) {
            self::DuplicateInFile,
            self::ExistingNameDiffers,
            self::CvFileContentsRepeated,
            self::ExistingMaterialsAtCapacity => CandidateImportIssueSeverity::Decision,
            self::ExistingContactPreserved,
            self::CvFilesUnreferenced,
            self::CvOmittedByReviewer,
            self::DuplicateNotChosen,
            self::ExcludedByReviewer => CandidateImportIssueSeverity::Notice,
            default => CandidateImportIssueSeverity::Blocking,
        };
    }

    /**
     * Is this about the row's CV — the file itself or the date that came with it?
     *
     * Deciding to import a contact without its document answers every one of
     * these at once, and none of the questions about who the contact is. The
     * received date belongs here because it describes the document: a row that
     * keeps no CV has nothing to have received.
     */
    public function concernsCv(): bool
    {
        return match ($this) {
            self::CvFilenameInvalid,
            self::CvFilenameTooLong,
            self::ReceivedOnInvalid,
            self::ReceivedOnInFuture,
            self::ReceivedOnWithoutCv,
            self::CvFileMissing,
            self::CvFileAmbiguous,
            self::CvFileSharedByRows,
            self::CvFileContentsRepeated,
            self::CvFileRejected,
            self::CvFileNameInvalid,
            self::CvFileEmpty,
            self::CvFileTooLarge,
            self::CvFileTypeUnsupported,
            self::CvFileUnreadable,
            self::CvFileStorageFailed,
            self::CvOmittedByReviewer => true,
            default => false,
        };
    }

    /** Is this about who the row's candidate is, rather than what it carries? */
    public function concernsIdentity(): bool
    {
        return match ($this) {
            self::EmailMissing,
            self::EmailInvalid,
            self::EmailTooLong,
            self::EmailAmbiguous,
            self::DuplicateInFile,
            self::ExistingNameDiffers => true,
            default => false,
        };
    }

    /** Does this issue make the row unimportable no matter what the reviewer picks? */
    public function isBlocking(): bool
    {
        return $this->severity() === CandidateImportIssueSeverity::Blocking;
    }
}
