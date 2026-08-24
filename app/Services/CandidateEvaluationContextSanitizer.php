<?php

namespace App\Services;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Data\BlindCandidateContext;
use App\Enums\ApplicationQuestionType;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\Candidate;
use Illuminate\Support\Str;

/**
 * Removes direct candidate identifiers from the material that is about to be
 * sent to the candidate-evaluation agent.
 *
 * Candidate identity is not relevant to fit, so it is not sent. This is
 * deterministic Laravel code on purpose: no model call decides what is an
 * identifier, and the rules can be read, tested and disagreed with.
 *
 * **What this is not.** It is not anonymisation, it does not eliminate bias, and
 * it makes no legal fairness guarantee. It removes identifiers with no
 * legitimate role in judging fit; indirect identity can still survive in prose,
 * and product copy must say only what is true — that direct candidate
 * identifiers are excluded from the AI evaluation context.
 *
 * **What is deliberately kept.** Employers, institutions, technologies, project
 * names, role titles, dates, tenure, qualifications, certifications,
 * accomplishments and numbers are evidence. Blind evaluation that destroys the
 * evidence evaluates nothing, so nothing is removed merely because it might
 * correlate with identity. There is no protected-characteristic detection here,
 * and no inference of race, gender or ethnicity — that would be an unreliable
 * classifier solving a problem nobody asked for.
 */
class CandidateEvaluationContextSanitizer
{
    use BuildsCompactAgentContext;

    public const NamePlaceholder = '[redacted-name]';

    public const EmailPlaceholder = '[redacted-email]';

    public const PhonePlaceholder = '[redacted-phone]';

    public const ProfilePlaceholder = '[redacted-profile]';

    /**
     * Shorter fragments are dropped: a two-letter name part word-matches far too
     * much ordinary prose to be worth redacting.
     */
    private const MinimumNameFragmentLength = 3;

    /**
     * Hosts whose URLs identify a person rather than describe their work. An
     * employer's or project's domain is evidence and stays.
     */
    private const ProfileHosts = [
        'linkedin.com',
        'github.com',
        'gitlab.com',
        'bitbucket.org',
        'twitter.com',
        'x.com',
        'instagram.com',
        'facebook.com',
        'tiktok.com',
        'threads.net',
        'medium.com',
        'behance.net',
        'dribbble.com',
        'wa.me',
        't.me',
    ];

    public function sanitize(Application $application, ?string $resumeText): BlindCandidateContext
    {
        $application->loadMissing(['candidate', 'answers']);

        $patterns = $this->patternsFor($application->candidate);
        $redactions = 0;

        $coverLetter = $this->redact($this->plainText($application->cover_letter_text), $patterns, $redactions);
        $resume = $this->redact($resumeText, $patterns, $redactions);

        $answers = $application->answers
            ->map(function (ApplicationAnswer $answer) use ($patterns, &$redactions): array {
                $value = $answer->response_type === ApplicationQuestionType::Number
                    ? ($answer->value_number === null ? null : (string) $answer->value_number)
                    : $answer->value_text;

                return [
                    'question' => (string) $this->redact($answer->question_snapshot, $patterns, $redactions),
                    'answer' => $this->redact($value, $patterns, $redactions),
                ];
            })
            ->values()
            ->all();

        /** @var list<array{question: string, answer: string|null}> $answers */
        return new BlindCandidateContext(
            resumeText: $resume,
            coverLetter: $coverLetter,
            answers: $answers,
            redactionCount: $redactions,
        );
    }

    /**
     * Ordered on purpose. Emails go first so a name inside an address is gone
     * before the name rules run; profile URLs go before phone rules so a
     * `wa.me/5511999999999` link is read as a profile rather than a number.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function patternsFor(?Candidate $candidate): array
    {
        $patterns = [];

        foreach ($this->storedEmailPatterns($candidate) as $pattern) {
            $patterns[] = [$pattern, self::EmailPlaceholder];
        }

        // Any address, not only the stored one: candidates routinely put a
        // second address in a resume header.
        $patterns[] = ['/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)*\.[\p{L}]{2,}/u', self::EmailPlaceholder];

        foreach ($this->storedProfilePatterns($candidate) as $pattern) {
            $patterns[] = [$pattern, self::ProfilePlaceholder];
        }

        $patterns[] = [$this->profileHostPattern(), self::ProfilePlaceholder];

        foreach ($this->storedPhonePatterns($candidate) as $pattern) {
            $patterns[] = [$pattern, self::PhonePlaceholder];
        }

        foreach ($this->genericPhonePatterns() as $pattern) {
            $patterns[] = [$pattern, self::PhonePlaceholder];
        }

        foreach ($this->storedNamePatterns($candidate) as $pattern) {
            $patterns[] = [$pattern, self::NamePlaceholder];
        }

        return $patterns;
    }

    /** @return list<string> */
    private function storedEmailPatterns(?Candidate $candidate): array
    {
        $email = $candidate?->getAttribute('email');

        return is_string($email) && trim($email) !== ''
            ? ['/'.preg_quote(trim($email), '/').'/iu']
            : [];
    }

    /**
     * Names are matched whole-word so a fragment never eats a longer technical
     * term ("Mark" must not touch "Marketing"). The full name is tried first so
     * it collapses to a single placeholder instead of one per part.
     *
     * @return list<string>
     */
    private function storedNamePatterns(?Candidate $candidate): array
    {
        $name = $candidate?->getAttribute('name');

        if (! is_string($name) || trim($name) === '') {
            return [];
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $fragments = Str::of($name)
            ->explode(' ')
            ->map(fn (string $part): string => trim($part, " \t.,;:'\""))
            ->filter(fn (string $part): bool => mb_strlen($part) >= self::MinimumNameFragmentLength)
            ->values()
            ->all();

        $candidates = array_values(array_unique([$name, ...$fragments]));

        // Longest first: matching "Ada Lovelace" before "Ada" keeps the output
        // readable rather than "[redacted-name] [redacted-name]".
        usort($candidates, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_map(
            fn (string $fragment): string => '/(?<![\p{L}\p{N}])'
                .preg_quote($fragment, '/')
                .'(?![\p{L}\p{N}])/iu',
            $candidates,
        );
    }

    /**
     * The stored number, tolerant of the separators people actually type. The
     * trailing nine digits are matched too, because a resume commonly omits the
     * country code the record carries.
     *
     * @return list<string>
     */
    private function storedPhonePatterns(?Candidate $candidate): array
    {
        $phone = $candidate?->getAttribute('phone');

        if (! is_string($phone)) {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (mb_strlen($digits) < 8) {
            return [];
        }

        $variants = [$digits];

        if (mb_strlen($digits) > 9) {
            $variants[] = mb_substr($digits, -9);
        }

        return array_map(
            fn (string $variant): string => '/(?<!\d)\+?'
                .implode('[\s().\-]{0,3}', array_map(
                    fn (string $digit): string => preg_quote($digit, '/'),
                    mb_str_split($variant),
                ))
                .'(?!\d)/u',
            array_values(array_unique($variants)),
        );
    }

    /**
     * Only shapes that are unambiguously a phone number: an international `+`
     * form, or a parenthesised area code.
     *
     * Anything looser would start eating the evidence. "2019 - 2023",
     * "1.2M requests/month" and "10.000.000" are all digit runs with separators,
     * and a generic "looks like a phone" rule redacts the metrics this product
     * exists to read. Numbers written without a phone marker are therefore left
     * alone, and the stored number above covers the realistic case.
     *
     * @return list<string>
     */
    private function genericPhonePatterns(): array
    {
        return [
            '/(?<![\p{N}\p{L}])\+\d[\d\s().\-]{7,18}\d(?!\d)/u',
            '/(?<!\d)\(\d{2,4}\)[\s.\-]?\d{3,5}[\s.\-]?\d{3,5}(?!\d)/u',
        ];
    }

    /** @return list<string> */
    private function storedProfilePatterns(?Candidate $candidate): array
    {
        $socials = $candidate?->getAttribute('socials');

        if (! is_array($socials)) {
            return [];
        }

        $patterns = [];

        foreach ($socials as $social) {
            $account = is_array($social) ? ($social['account'] ?? null) : null;

            if (! is_string($account) || trim($account) === '') {
                continue;
            }

            $account = trim($account);
            $patterns[] = '/'.preg_quote($account, '/').'/iu';

            // A handle stored as a bare "@ada" or as the tail of a profile URL
            // is the same identifier written two ways.
            $handle = ltrim((string) Str::of($account)->afterLast('/')->afterLast('@'), '@');

            if (mb_strlen($handle) >= self::MinimumNameFragmentLength && ! Str::contains($handle, '.')) {
                $patterns[] = '/@?(?<![\p{L}\p{N}])'.preg_quote($handle, '/').'(?![\p{L}\p{N}])/iu';
            }
        }

        return array_values(array_unique($patterns));
    }

    private function profileHostPattern(): string
    {
        $hosts = implode('|', array_map(
            fn (string $host): string => preg_quote($host, '/'),
            self::ProfileHosts,
        ));

        return '/(?:https?:\/\/)?(?:[\p{L}\p{N}\-]+\.)?(?:'.$hosts.')\/[\p{L}\p{N}._~%\-\/]+/iu';
    }

    /**
     * @param  list<array{0: string, 1: string}>  $patterns
     */
    private function redact(?string $value, array $patterns, int &$redactions): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach ($patterns as [$pattern, $replacement]) {
            $count = 0;
            $replaced = preg_replace($pattern, $replacement, $value, -1, $count);

            if ($replaced === null) {
                continue;
            }

            $value = $replaced;
            $redactions += $count;
        }

        $collapsed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $collapsed === '' ? null : $collapsed;
    }
}
