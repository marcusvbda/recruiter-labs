<?php

namespace App\Services;

use App\Data\CandidateImportCsvError;
use App\Data\CandidateImportCsvReading;
use App\Data\CandidateImportCsvRecord;
use App\Enums\CandidateImportCsvErrorCode;
use App\Enums\CandidateImportCsvMode;
use App\Enums\CandidateImportCsvSeparator;
use Generator;

/**
 * Turns an uploaded candidate CSV into numbered logical records.
 *
 * This is the whole of the reading stage: structure, encoding, headers, record
 * numbering and correction-file decoding. It never decides whether a name, an
 * email address or a date is acceptable, never touches the database and never
 * looks at the uploaded CVs — the value of a cell is the next stage's problem,
 * and keeping the two apart is what makes both of them testable.
 *
 * Record numbering is the part downstream code depends on most. The header is
 * record 1, a line break inside a quoted value stays inside its record, and an
 * entirely empty record is skipped but still spends its number. The recruiter
 * fixes rows in the file they uploaded, so every number reported back has to
 * match what their editor shows.
 *
 * PHP's own CSV functions were not used: they accept malformed quoting silently
 * and count physical lines, and the product has to refuse the first and report
 * the second exactly. The parser below reads the file in chunks and never holds
 * more than one record plus one buffer beyond the accepted rows.
 */
class CandidateImportCsvReader
{
    /** Every header the contract defines, in template order. */
    public const HEADERS = ['name', 'email', 'phone', 'linkedin_url', 'cv_filename', 'received_on', 'source_label'];

    /** Headers that must be present, whatever the order of the columns. */
    public const REQUIRED_HEADERS = ['name', 'email'];

    /**
     * How many records may report a missing correction marker before reading stops.
     *
     * A correction file re-saved by a spreadsheet loses the marker on every cell
     * at once, and a thousand identical errors help nobody: the first few show
     * the convention was broken and that the file has to be exported again.
     */
    private const MAX_REPORTED_MARKER_RECORDS = 20;

    private const CHUNK_BYTES = 8192;

    private const BOM = "\xEF\xBB\xBF";

    private const STATE_FIELD_START = 0;

    private const STATE_UNQUOTED = 1;

    private const STATE_QUOTED = 2;

    private const STATE_QUOTE_CLOSED = 3;

    /**
     * Read one CSV file from disk.
     *
     * The separator and the mode are always supplied by whoever uploaded the
     * file. Neither is ever inferred: a guessed separator can merge seven
     * columns into one that still parses, and a guessed mode either eats a
     * legitimate leading apostrophe or leaves a protection marker inside a name.
     */
    public function read(
        string $path,
        CandidateImportCsvSeparator $separator = CandidateImportCsvSeparator::Comma,
        CandidateImportCsvMode $mode = CandidateImportCsvMode::Template,
    ): CandidateImportCsvReading {
        $size = is_file($path) ? filesize($path) : false;
        if ($size === false) {
            return CandidateImportCsvReading::refused([new CandidateImportCsvError(CandidateImportCsvErrorCode::Unreadable)]);
        }
        if ($size > CandidateImportLimits::CSV_BYTES) {
            return CandidateImportCsvReading::refused([new CandidateImportCsvError(
                CandidateImportCsvErrorCode::TooLarge,
                context: ['limit' => CandidateImportLimits::CSV_BYTES, 'found' => $size],
            )]);
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return CandidateImportCsvReading::refused([new CandidateImportCsvError(CandidateImportCsvErrorCode::Unreadable)]);
        }

        try {
            return $this->parse($stream, $separator, $mode);
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function parse($stream, CandidateImportCsvSeparator $separator, CandidateImportCsvMode $mode): CandidateImportCsvReading
    {
        /** @var list<string> $headers */
        $headers = [];
        /** @var list<CandidateImportCsvRecord> $records */
        $records = [];
        /** @var list<CandidateImportCsvError> $markerErrors */
        $markerErrors = [];
        $headerFields = 0;
        $ignored = 0;
        $total = 0;
        $rows = 0;
        $completed = true;
        $generator = $this->records($stream, $separator->value);

        foreach ($generator as $number => $cells) {
            $total = $number;
            foreach ($cells as $cell) {
                if (! mb_check_encoding($cell, 'UTF-8')) {
                    return CandidateImportCsvReading::refused([
                        new CandidateImportCsvError(CandidateImportCsvErrorCode::InvalidEncoding, $number),
                    ]);
                }
            }

            if ($number === 1) {
                $identifiers = array_map(fn (string $cell): string => mb_strtolower(trim($cell)), $cells);
                $headerErrors = $this->headerErrors($identifiers);
                if ($headerErrors !== []) {
                    return CandidateImportCsvReading::refused($headerErrors);
                }
                $headers = $identifiers;
                $headerFields = count($cells);

                continue;
            }

            if ($this->isEmptyRecord($cells, $mode)) {
                $ignored++;

                continue;
            }

            if (count($cells) !== $headerFields) {
                return CandidateImportCsvReading::refused([new CandidateImportCsvError(
                    CandidateImportCsvErrorCode::InconsistentFieldCount,
                    $number,
                    context: ['expected' => $headerFields, 'found' => count($cells)],
                )]);
            }

            $rows++;
            if ($rows > CandidateImportLimits::ROWS) {
                return CandidateImportCsvReading::refused([new CandidateImportCsvError(
                    CandidateImportCsvErrorCode::TooManyRows,
                    $number,
                    context: ['limit' => CandidateImportLimits::ROWS],
                )]);
            }

            $values = $this->decode($cells, $headers, $mode, $number, $markerErrors);
            if (count($markerErrors) >= self::MAX_REPORTED_MARKER_RECORDS) {
                $completed = false;

                break;
            }
            if ($markerErrors === []) {
                $records[] = new CandidateImportCsvRecord($number, $values);
            }
        }

        if ($completed) {
            $failure = $generator->getReturn();
            if ($failure !== null) {
                return CandidateImportCsvReading::refused([new CandidateImportCsvError(
                    $failure,
                    $failure === CandidateImportCsvErrorCode::MalformedQuoting ? $total + 1 : null,
                )]);
            }
        }

        if ($markerErrors !== []) {
            return CandidateImportCsvReading::refused($markerErrors);
        }
        if ($records === []) {
            return CandidateImportCsvReading::refused([new CandidateImportCsvError(CandidateImportCsvErrorCode::EmptyFile)]);
        }

        return new CandidateImportCsvReading($headers, $records, $ignored, $total);
    }

    /**
     * Validate the header record as a whole.
     *
     * Every problem found is reported: a file with one unknown column and one
     * missing required column should say both, because the person fixing it
     * edits the header once. Nothing is mapped by resemblance — an unrecognised
     * column is never quietly attached to an internal field.
     *
     * @param  list<string>  $identifiers  Normalized header text, in file order.
     * @return list<CandidateImportCsvError>
     */
    private function headerErrors(array $identifiers): array
    {
        $errors = [];
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($identifiers as $identifier) {
            if ($identifier === '' || ! in_array($identifier, self::HEADERS, true)) {
                $errors[] = new CandidateImportCsvError(
                    CandidateImportCsvErrorCode::UnknownHeader,
                    1,
                    $identifier === '' ? null : $identifier,
                    ['header' => mb_substr($identifier, 0, 64)],
                );

                continue;
            }
            if (isset($seen[$identifier])) {
                $errors[] = new CandidateImportCsvError(CandidateImportCsvErrorCode::DuplicateHeader, 1, $identifier);

                continue;
            }
            $seen[$identifier] = true;
        }

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! isset($seen[$required])) {
                $errors[] = new CandidateImportCsvError(CandidateImportCsvErrorCode::MissingRequiredHeader, 1, $required);
            }
        }

        return $errors;
    }

    /**
     * Is this record nothing but empty cells?
     *
     * Such a record is ignored rather than refused, and a correction file's
     * protection markers are looked through first so that a row of markers is
     * still recognised as empty.
     *
     * @param  list<string>  $cells
     */
    private function isEmptyRecord(array $cells, CandidateImportCsvMode $mode): bool
    {
        foreach ($cells as $cell) {
            $value = trim($cell);
            if ($mode === CandidateImportCsvMode::CorrectionFile && str_starts_with($value, CandidateImportCsvMode::PROTECTION_MARKER)) {
                $value = trim(substr($value, 1));
            }
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Key one record's cells by header, removing the correction marker first.
     *
     * Exactly one leading apostrophe is removed in correction mode, so an
     * exported `'+353871234567` comes back as `+353871234567` and a name that
     * genuinely starts with an apostrophe, exported with two, keeps one. The
     * marker is gone before anything measures or validates the value, so it can
     * never spend a character of a field's length. Template mode does not touch
     * apostrophes at all.
     *
     * A nonempty cell without its marker means the convention was broken while
     * the file was edited. That is reported for the record — once, on the first
     * such cell — because deciding whether the missing character was a marker or
     * part of the value would be a guess about the recruiter's data.
     *
     * @param  list<string>  $cells
     * @param  list<string>  $headers
     * @param  list<CandidateImportCsvError>  $markerErrors
     * @return array<string, string>
     */
    private function decode(array $cells, array $headers, CandidateImportCsvMode $mode, int $number, array &$markerErrors): array
    {
        $values = [];

        foreach ($headers as $index => $header) {
            $cell = $cells[$index];
            if ($mode === CandidateImportCsvMode::CorrectionFile && $cell !== '') {
                if (! str_starts_with($cell, CandidateImportCsvMode::PROTECTION_MARKER)) {
                    $markerErrors[] = new CandidateImportCsvError(
                        CandidateImportCsvErrorCode::MissingProtectionMarker,
                        $number,
                        $header,
                    );

                    break;
                }
                $cell = substr($cell, 1);
            }
            $values[$header] = $cell;
        }

        return $values;
    }

    /**
     * Stream the file as logical records, numbered from the header.
     *
     * The state machine reads one byte at a time so that a quoted value may hold
     * separators, doubled quotes and line breaks without ending its record, and
     * so that a record split across two chunks is still one record. Both LF and
     * CRLF end a record; a blank line ends an empty one, which the caller counts
     * and ignores. A trailing newline does not invent a final record.
     *
     * Reading stops at the first structural failure, which the generator returns
     * instead of yielding: a quoted field that never closes, or content after a
     * closing quote. A quote inside an unquoted field is kept as an ordinary
     * character, the way every spreadsheet reads it.
     *
     * @param  resource  $stream
     * @return Generator<int, list<string>, mixed, CandidateImportCsvErrorCode|null>
     */
    private function records($stream, string $separator): Generator
    {
        $state = self::STATE_FIELD_START;
        $field = '';
        /** @var list<string> $fields */
        $fields = [];
        $number = 0;
        $pending = false;
        $swallowLineFeed = false;
        $start = true;

        while (! feof($stream)) {
            $chunk = fread($stream, self::CHUNK_BYTES);
            if ($chunk === false) {
                return CandidateImportCsvErrorCode::Unreadable;
            }
            if ($chunk === '') {
                break;
            }
            if ($start) {
                $start = false;
                if (str_starts_with($chunk, self::BOM)) {
                    $chunk = substr($chunk, strlen(self::BOM));
                }
            }

            $length = strlen($chunk);
            for ($i = 0; $i < $length; $i++) {
                $character = $chunk[$i];
                if ($swallowLineFeed) {
                    $swallowLineFeed = false;
                    if ($character === "\n") {
                        continue;
                    }
                }

                if ($state === self::STATE_QUOTED) {
                    if ($character === '"') {
                        $state = self::STATE_QUOTE_CLOSED;

                        continue;
                    }
                    $field .= $character;

                    continue;
                }

                if ($state === self::STATE_QUOTE_CLOSED && $character === '"') {
                    $field .= '"';
                    $state = self::STATE_QUOTED;

                    continue;
                }

                if ($character === $separator) {
                    $fields[] = $field;
                    $field = '';
                    $state = self::STATE_FIELD_START;
                    $pending = true;

                    continue;
                }

                if ($character === "\n" || $character === "\r") {
                    $swallowLineFeed = $character === "\r";
                    $fields[] = $field;
                    $number++;
                    yield $number => $fields;
                    $fields = [];
                    $field = '';
                    $state = self::STATE_FIELD_START;
                    $pending = false;

                    continue;
                }

                if ($state === self::STATE_QUOTE_CLOSED) {
                    return CandidateImportCsvErrorCode::MalformedQuoting;
                }

                if ($state === self::STATE_FIELD_START && $character === '"') {
                    $state = self::STATE_QUOTED;
                    $pending = true;

                    continue;
                }

                $field .= $character;
                $state = self::STATE_UNQUOTED;
                $pending = true;
            }
        }

        if ($state === self::STATE_QUOTED) {
            return CandidateImportCsvErrorCode::MalformedQuoting;
        }

        if ($pending) {
            $fields[] = $field;
            $number++;
            yield $number => $fields;
        }

        return null;
    }
}
