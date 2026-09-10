<?php

namespace App\Services;

use App\Data\CandidateImportCsvRecord;
use App\Data\CandidateImportFields;
use App\Data\CandidateImportIssue;
use App\Enums\CandidateImportIssueCode;
use App\Models\Candidate;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Judges one record's cells, one field at a time.
 *
 * Nothing here looks at the database, at the uploaded CVs or at the other rows:
 * a field is acceptable or it is not, and that answer must not depend on what
 * the workspace already contains. Identity, duplicates and capacity are the
 * resolver's work; keeping them out of here is what makes both halves small
 * enough to reason about.
 *
 * The rules are deliberately unforgiving in one direction only. A value is
 * never repaired, completed or guessed at — no country code is inferred for a
 * phone number, no date is derived from a filename, no email alias is rewritten
 * into a "canonical" form. A refused cell becomes an issue and a null value, so
 * the recruiter corrects their file rather than discovering later that the
 * product invented something on their behalf.
 */
class CandidateImportFieldValidator
{
    public const NAME_LIMIT = 255;

    public const EMAIL_LIMIT = 255;

    public const LINKEDIN_URL_LIMIT = 255;

    public const CV_FILENAME_LIMIT = 255;

    public const SOURCE_LABEL_LIMIT = 120;

    /** Digits allowed after the leading `+`, as E.164 defines them. */
    private const PHONE_MIN_DIGITS = 7;

    private const PHONE_MAX_DIGITS = 15;

    /** Hosts that serve personal LinkedIn profiles; nothing else is accepted. */
    private const LINKEDIN_HOSTS = ['linkedin.com', 'www.linkedin.com'];

    /** Whitespace removed from the ends of every cell, including the shapes spreadsheets leave behind. */
    private const OUTER_WHITESPACE = '/^[\s\x{00A0}\x{200B}\x{FEFF}]+|[\s\x{00A0}\x{200B}\x{FEFF}]+$/u';

    /**
     * Validate one record against the field contract.
     *
     * `$batchSourceLabel` is already trimmed and known to be usable; a row that
     * supplies no label of its own inherits it, which is why an empty
     * `source_label` cell is never an error.
     *
     * `$timezone` decides what "today" means when a received date is checked
     * against the future. It is the workspace's displayed timezone when there is
     * one, so a recruiter in Auckland is not told that today is tomorrow.
     *
     * @return array{fields: CandidateImportFields, issues: list<CandidateImportIssue>}
     */
    public function validate(CandidateImportCsvRecord $record, string $batchSourceLabel, string $timezone): array
    {
        /** @var list<CandidateImportIssue> $issues */
        $issues = [];

        $name = $this->name($record->value('name'), $issues);
        [$email, $normalizedEmail] = $this->email($record->value('email'), $issues);
        $phone = $this->phone($record->value('phone'), $issues);
        $linkedinUrl = $this->linkedinUrl($record->value('linkedin_url'), $issues);
        $cvFilename = $this->cvFilename($record->value('cv_filename'), $issues);
        $receivedOn = $this->receivedOn($record->value('received_on'), $cvFilename, $timezone, $issues);
        $sourceLabel = $this->sourceLabel($record->value('source_label'), $batchSourceLabel, $issues);

        return [
            'fields' => new CandidateImportFields(
                name: $name,
                email: $email,
                normalizedEmail: $normalizedEmail,
                phone: $phone,
                linkedinUrl: $linkedinUrl,
                cvFilename: $cvFilename,
                receivedOn: $receivedOn,
                sourceLabel: $sourceLabel,
            ),
            'issues' => $issues,
        ];
    }

    /** Remove the outer whitespace of a cell without touching anything inside it. */
    public function trimOuter(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) preg_replace(self::OUTER_WHITESPACE, '', $value);
    }

    /** Is a batch source label usable? One to 120 characters once trimmed. */
    public function isUsableSourceLabel(string $label): bool
    {
        $trimmed = $this->trimOuter($label);

        return $trimmed !== '' && mb_strlen($trimmed) <= self::SOURCE_LABEL_LIMIT;
    }

    /**
     * The candidate's name, exactly as they spell it.
     *
     * Accents, punctuation, particles and display capitalization are all part of
     * a person's name; the only thing removed is the whitespace around it. A
     * name is required on every row because a candidate record without one is
     * unusable in every screen that lists it.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    private function name(?string $value, array &$issues): ?string
    {
        $name = $this->trimOuter($value);

        if ($name === '') {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::NameMissing, 'name');

            return null;
        }

        if (mb_strlen($name) > self::NAME_LIMIT) {
            $issues[] = new CandidateImportIssue(
                CandidateImportIssueCode::NameTooLong,
                'name',
                ['limit' => self::NAME_LIMIT, 'found' => mb_strlen($name)],
            );

            return null;
        }

        return $name;
    }

    /**
     * The one address that decides identity.
     *
     * Case and surrounding whitespace do not distinguish two addresses, so the
     * normalized form is what identity is resolved on. Nothing beyond that is
     * touched: dots and `+tags` are meaningful to some providers and part of the
     * address to all of them, and rewriting them would quietly merge two people
     * who deliberately keep separate inboxes.
     *
     * @param  list<CandidateImportIssue>  $issues
     * @return array{0: string|null, 1: string|null}
     */
    private function email(?string $value, array &$issues): array
    {
        $email = $this->trimOuter($value);

        if ($email === '') {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::EmailMissing, 'email');

            return [null, null];
        }

        if (mb_strlen($email) > self::EMAIL_LIMIT) {
            $issues[] = new CandidateImportIssue(
                CandidateImportIssueCode::EmailTooLong,
                'email',
                ['limit' => self::EMAIL_LIMIT, 'found' => mb_strlen($email)],
            );

            return [null, null];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::EmailInvalid, 'email');

            return [null, null];
        }

        return [$email, Candidate::normalizeEmail($email)];
    }

    /**
     * An international number, or nothing.
     *
     * Grouping characters a person would type — spaces, parentheses, hyphens,
     * periods — are removed before the number is measured, and the result keeps
     * its `+` so that a value which passed through a spreadsheet as a number is
     * refused instead of being stored as a meaningless integer. No country code
     * is inferred and no extension is accepted: a number the product cannot dial
     * is worse than an empty field, because it looks reliable.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    private function phone(?string $value, array &$issues): ?string
    {
        $phone = $this->trimOuter($value);

        if ($phone === '') {
            return null;
        }

        $compact = (string) preg_replace('/[\s\x{00A0}().\-]/u', '', $phone);

        if (! str_starts_with($compact, '+')) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::PhoneInvalid, 'phone', ['reason' => 'missing_country_prefix']);

            return null;
        }

        $digits = substr($compact, 1);

        if (preg_match('/^\d+$/', $digits) !== 1) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::PhoneInvalid, 'phone', ['reason' => 'unsupported_characters']);

            return null;
        }

        if (str_starts_with($digits, '0')) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::PhoneInvalid, 'phone', ['reason' => 'leading_zero']);

            return null;
        }

        $length = strlen($digits);

        if ($length < self::PHONE_MIN_DIGITS || $length > self::PHONE_MAX_DIGITS) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::PhoneInvalid, 'phone', [
                'reason' => 'digit_count',
                'minimum' => self::PHONE_MIN_DIGITS,
                'maximum' => self::PHONE_MAX_DIGITS,
                'found' => $length,
            ]);

            return null;
        }

        return '+'.$digits;
    }

    /**
     * A LinkedIn profile address, stored as a contact link and nothing more.
     *
     * It has to be an absolute HTTPS URL on LinkedIn's own host with a nonempty
     * `/in/` profile path: a company page, a search result or a shortener says
     * nothing about who this person is. The address is never fetched, resolved
     * or enriched — the product stores what the recruiter supplied so they can
     * open it themselves.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    private function linkedinUrl(?string $value, array &$issues): ?string
    {
        $url = $this->trimOuter($value);

        if ($url === '') {
            return null;
        }

        if (mb_strlen($url) > self::LINKEDIN_URL_LIMIT) {
            $issues[] = new CandidateImportIssue(
                CandidateImportIssueCode::LinkedinUrlTooLong,
                'linkedin_url',
                ['limit' => self::LINKEDIN_URL_LIMIT, 'found' => mb_strlen($url)],
            );

            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::LinkedinUrlInvalid, 'linkedin_url', ['reason' => 'not_absolute']);

            return null;
        }

        if (mb_strtolower($parts['scheme']) !== 'https') {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::LinkedinUrlInvalid, 'linkedin_url', ['reason' => 'not_https']);

            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::LinkedinUrlInvalid, 'linkedin_url', ['reason' => 'credentials_in_url']);

            return null;
        }

        if (! in_array(mb_strtolower($parts['host']), self::LINKEDIN_HOSTS, true)) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::LinkedinUrlInvalid, 'linkedin_url', ['reason' => 'unsupported_host']);

            return null;
        }

        $path = $parts['path'] ?? '';

        if (preg_match('#^/in/([^/?\#]+)#i', $path, $matches) !== 1 || trim(rawurldecode($matches[1])) === '') {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::LinkedinUrlInvalid, 'linkedin_url', ['reason' => 'not_a_profile_path']);

            return null;
        }

        return $url;
    }

    /**
     * The name of one CV file, used only to match an upload.
     *
     * This is an association key, not a location: anything that could point
     * somewhere — a path separator, a traversal component, a drive letter, a
     * remote URL — is refused, and so is any control character, because the
     * value is compared against filenames the recruiter also uploads. A `.pdf`
     * at the end of a URL does not make it a filename.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    private function cvFilename(?string $value, array &$issues): ?string
    {
        $filename = $this->trimOuter($value);

        if ($filename === '') {
            return null;
        }

        if (mb_strlen($filename) > self::CV_FILENAME_LIMIT) {
            $issues[] = new CandidateImportIssue(
                CandidateImportIssueCode::CvFilenameTooLong,
                'cv_filename',
                ['limit' => self::CV_FILENAME_LIMIT, 'found' => mb_strlen($filename)],
            );

            return null;
        }

        $reason = $this->cvFilenameRejection($filename);

        if ($reason !== null) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::CvFilenameInvalid, 'cv_filename', ['reason' => $reason]);

            return null;
        }

        return $filename;
    }

    /** The first reason this value is not a bare filename, or null when it is one. */
    private function cvFilenameRejection(string $filename): ?string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $filename) === 1) {
            return 'control_character';
        }

        if (preg_match('#^[A-Za-z][A-Za-z0-9+.\-]*:#', $filename) === 1) {
            return 'remote_reference';
        }

        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return 'path_separator';
        }

        if ($filename === '.' || $filename === '..' || str_starts_with($filename, '..')) {
            return 'traversal';
        }

        $dot = mb_strrpos($filename, '.');

        if ($dot === false || $dot === 0 || $dot === mb_strlen($filename) - 1) {
            return 'missing_extension';
        }

        return null;
    }

    /**
     * The day the CV arrived, when the recruiter actually knows it.
     *
     * An unknown date stays empty. It is never inferred from a filename, from
     * the contents or document properties of a CV, or from the day the import
     * ran — a made-up date would silently become part of the candidate's record
     * and could not be told apart from one the recruiter typed. A date without a
     * CV to attach it to is an error rather than a stored fact, because there is
     * nothing for it to describe.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    private function receivedOn(?string $value, ?string $cvFilename, string $timezone, array &$issues): ?string
    {
        $received = $this->trimOuter($value);

        if ($received === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $received) !== 1) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ReceivedOnInvalid, 'received_on', ['reason' => 'unsupported_format']);

            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $received, $timezone);
        } catch (Throwable) {
            $date = null;
        }

        if ($date === null || $date->format('Y-m-d') !== $received) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ReceivedOnInvalid, 'received_on', ['reason' => 'not_a_calendar_date']);

            return null;
        }

        if ($date->isAfter(CarbonImmutable::now($timezone)->startOfDay())) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ReceivedOnInFuture, 'received_on', ['timezone' => $timezone]);

            return null;
        }

        if ($cvFilename === null) {
            $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ReceivedOnWithoutCv, 'received_on');

            return null;
        }

        return $received;
    }

    /**
     * Where this candidate came from, per row or per batch.
     *
     * An empty cell is not missing information: it means "the same place as the
     * rest of this file", so the row inherits the batch label the recruiter
     * already had to supply.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    private function sourceLabel(?string $value, string $batchSourceLabel, array &$issues): ?string
    {
        $label = $this->trimOuter($value);

        if ($label === '') {
            return $batchSourceLabel;
        }

        if (mb_strlen($label) > self::SOURCE_LABEL_LIMIT) {
            $issues[] = new CandidateImportIssue(
                CandidateImportIssueCode::SourceLabelTooLong,
                'source_label',
                ['limit' => self::SOURCE_LABEL_LIMIT, 'found' => mb_strlen($label)],
            );

            return null;
        }

        return $label;
    }
}
