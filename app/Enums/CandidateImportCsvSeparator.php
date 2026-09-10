<?php

namespace App\Enums;

/**
 * The field separator of an uploaded candidate CSV.
 *
 * The separator is always an explicit choice made by the person uploading the
 * file: guessing it from the contents can silently turn one column into seven,
 * or seven into one, and the resulting import would look plausible while being
 * wrong. Comma is the default and the separator used by the template.
 */
enum CandidateImportCsvSeparator: string
{
    case Comma = ',';
    case Semicolon = ';';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $separator): string => $separator->value, self::cases());
    }
}
