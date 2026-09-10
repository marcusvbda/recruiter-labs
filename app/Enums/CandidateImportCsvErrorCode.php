<?php

namespace App\Enums;

/**
 * Why an uploaded candidate CSV could not be read at all.
 *
 * Every case here stops the whole file: none of them can be repaired by
 * dropping a single row, so the reader reports the reason and produces no
 * records. The codes are returned as data and translated by the interface that
 * shows them, which keeps the reader usable from a queue worker or a console
 * command where no request locale exists.
 */
enum CandidateImportCsvErrorCode: string
{
    /** The file could not be opened or read from storage. */
    case Unreadable = 'unreadable';

    /** Larger than the accepted CSV size. */
    case TooLarge = 'too_large';

    /** Bytes that are not valid UTF-8, with or without a byte-order mark. */
    case InvalidEncoding = 'invalid_encoding';

    /** No header record at all, or a header record with no usable columns. */
    case EmptyFile = 'empty_file';

    /** An unterminated quoted field, or content after a closing quote. */
    case MalformedQuoting = 'malformed_quoting';

    /** A data record with more or fewer fields than the header. */
    case InconsistentFieldCount = 'inconsistent_field_count';

    /** `name` or `email` is absent. */
    case MissingRequiredHeader = 'missing_required_header';

    /** Two headers that normalize to the same identifier. */
    case DuplicateHeader = 'duplicate_header';

    /** A header that is not part of the documented contract. */
    case UnknownHeader = 'unknown_header';

    /** More candidate data records than the accepted row limit. */
    case TooManyRows = 'too_many_rows';

    /** A correction file whose nonempty cell lost its protection marker. */
    case MissingProtectionMarker = 'missing_protection_marker';
}
