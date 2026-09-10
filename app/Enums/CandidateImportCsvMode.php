<?php

namespace App\Enums;

/**
 * How the cells of an uploaded candidate CSV must be decoded.
 *
 * A correction file is the same format with one reversible text-protection
 * marker per nonempty cell, so that a value such as `+353871234567` survives a
 * spreadsheet round-trip. Choosing that mode is a format declaration, never
 * extra authorization, and it is never detected from the contents: a template
 * file must keep a literal leading apostrophe, and a correction file must lose
 * exactly one.
 */
enum CandidateImportCsvMode: string
{
    case Template = 'template';
    case CorrectionFile = 'correction_file';

    /** The single leading character that protects a cell in a correction file. */
    public const PROTECTION_MARKER = "'";

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $mode): string => $mode->value, self::cases());
    }
}
