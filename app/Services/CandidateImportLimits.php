<?php

namespace App\Services;

class CandidateImportLimits
{
    public const CSV_BYTES = 5 * 1048576;

    public const ROWS = 1000;

    public const CV_BYTES = 10 * 1048576;

    public const BATCH_FILES = 100;

    public const BATCH_CV_BYTES = 250 * 1048576;

    public const RETAINED_MATERIALS = 20;

    public const UNCONFIRMED_BATCHES = 3;

    public const RETENTION_DAYS = 7;

    public const STALLED_MINUTES = 30;

    public const TEXT_CHARACTERS = 6000;
}
