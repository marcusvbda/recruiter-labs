<?php

namespace App\Enums;

/**
 * How much a row issue costs the recruiter.
 *
 * The three levels are not degrees of unhappiness: they say what has to happen
 * before the row can be imported. Blocking means the file itself is wrong and
 * has to be corrected; Decision means the data is understood but the product
 * refuses to choose on the recruiter's behalf; Notice means the row will import
 * exactly as previewed and is only telling the recruiter what it will not do.
 */
enum CandidateImportIssueSeverity: string
{
    /** The row cannot be imported as supplied, whatever the reviewer decides. */
    case Blocking = 'blocking';

    /** The row is importable only after a deliberate choice by the reviewer. */
    case Decision = 'decision';

    /** Disclosure only; the row imports without further input. */
    case Notice = 'notice';
}
