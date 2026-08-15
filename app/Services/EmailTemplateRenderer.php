<?php

namespace App\Services;

use App\Models\Application;

/**
 * The one template system for recruiter-authored candidate emails.
 *
 * Templates are plain text with `{{ token }}` placeholders. Tokens come from a
 * fixed catalog — nothing is read reflectively off the models — so no token can
 * ever expose credentials, tokens or internal infrastructure fields, and no
 * template can execute code. Unknown or unresolvable tokens render as an empty
 * string rather than leaking the raw placeholder to the candidate.
 */
class EmailTemplateRenderer
{
    /**
     * Every `{{ … }}` is consumed, not just the known ones: an unrecognised
     * placeholder disappears rather than reaching the candidate as raw syntax.
     */
    private const TOKEN_PATTERN = '/\{\{\s*([^{}]*?)\s*\}\}/';

    /**
     * The variables offered to recruiters, grouped for display.
     *
     * @return array<string, list<string>>
     */
    public static function catalog(): array
    {
        return [
            'candidate' => ['candidate.name', 'candidate.email', 'candidate.phone'],
            'job' => ['job.title', 'job.url'],
            'company' => ['company.name'],
            'application' => ['application.status', 'application.date'],
        ];
    }

    /** @return list<string> */
    public static function tokens(): array
    {
        return array_merge(...array_values(self::catalog()));
    }

    /**
     * The literal placeholder a recruiter types for a token. Built here rather
     * than in a Blade view: a literal `{{ … }}` written in a template is compiled
     * by Blade as a real echo, so the braces must never appear in view source.
     */
    public static function placeholder(string $token): string
    {
        return '{{ '.$token.' }}';
    }

    /**
     * The catalog with each token's ready-to-copy placeholder.
     *
     * @return array<string, array<string, string>> group => [token => placeholder]
     */
    public static function placeholderCatalog(): array
    {
        return array_map(
            fn (array $tokens): array => array_combine(
                $tokens,
                array_map(self::placeholder(...), $tokens),
            ),
            self::catalog(),
        );
    }

    /**
     * @param  bool  $escape  True for HTML bodies, false for plain-text subjects.
     */
    public function render(?string $template, Application $application, bool $escape = false): string
    {
        if (blank($template)) {
            return '';
        }

        $values = $this->values($application);

        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $matches) use ($values, $escape): string {
                $value = $values[mb_strtolower($matches[1])] ?? '';

                return $escape ? e($value) : $value;
            },
            $template,
        );
    }

    /**
     * @return array<string, string>
     */
    public function values(Application $application): array
    {
        $application->loadMissing(['candidate', 'job', 'company', 'status']);

        return [
            'candidate.name' => (string) $application->candidate?->name,
            'candidate.email' => (string) $application->candidate?->email,
            'candidate.phone' => (string) $application->candidate?->phone,
            'job.title' => (string) $application->job?->name,
            'job.url' => $application->job === null
                ? ''
                : route('job.show', ['key' => $application->job->key]),
            'company.name' => (string) $application->company?->name,
            'application.status' => (string) $application->status?->name,
            'application.date' => $application->created_at?->translatedFormat('d M Y') ?? '',
        ];
    }

    /**
     * Sample values used to preview a template without a real application.
     *
     * @return array<string, string>
     */
    public static function sampleValues(): array
    {
        return [
            'candidate.name' => __('pipelines.variables.samples.candidate_name'),
            'candidate.email' => 'candidate@example.com',
            'candidate.phone' => '+353 85 123 4567',
            'job.title' => __('pipelines.variables.samples.job_title'),
            'job.url' => url('/'),
            'company.name' => __('pipelines.variables.samples.company_name'),
            'application.status' => __('pipelines.variables.samples.application_status'),
            'application.date' => now()->translatedFormat('d M Y'),
        ];
    }
}
