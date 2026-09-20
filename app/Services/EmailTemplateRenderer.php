<?php

namespace App\Services;

use App\Data\EmailTemplateContext;
use App\Models\EmailTemplate;

/**
 * The one template system for recruiter-authored candidate emails.
 *
 * Templates are plain text with `{{ token }}` placeholders. Tokens come from a
 * fixed catalog — nothing is read reflectively off the models — so no token can
 * ever expose credentials, tokens or internal infrastructure fields, and no
 * template can execute code. Unknown or unresolvable tokens render as an empty
 * string rather than leaking the raw placeholder to the candidate.
 *
 * Rendering takes an {@see EmailTemplateContext} rather than an Application:
 * the same reusable template must be resolvable from a candidate alone, from a
 * candidate plus a job, or from a full application.
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
    public function render(?string $template, EmailTemplateContext $context, bool $escape = false): string
    {
        return $this->substitute($template, $this->values($context), $escape);
    }

    /**
     * The template's subject resolved against a context. Plain text, so token
     * values are never escaped.
     */
    public function renderSubject(EmailTemplate $template, EmailTemplateContext $context): string
    {
        return $this->render($template->subject, $context);
    }

    /**
     * The template's body resolved against a context. The body is HTML, so
     * token values are escaped before they are substituted into it.
     */
    public function renderBody(EmailTemplate $template, EmailTemplateContext $context): string
    {
        return $this->render($template->body, $context, escape: true);
    }

    /**
     * Renders a template against sample values, for previewing a template that
     * has no real recipient yet.
     */
    public function preview(?string $template, bool $escape = false): string
    {
        return $this->substitute($template, self::sampleValues(), $escape);
    }

    /**
     * The catalog tokens a template uses that the given context cannot fill —
     * e.g. `job.title` when no job is in play. Callers use this to say what is
     * missing instead of sending a message with holes in it.
     *
     * @return list<string>
     */
    public function unresolvedTokens(?string $template, EmailTemplateContext $context): array
    {
        if (blank($template)) {
            return [];
        }

        $values = $this->values($context);
        $used = [];

        preg_match_all(self::TOKEN_PATTERN, $template, $matches);

        foreach ($matches[1] as $token) {
            $token = mb_strtolower($token);

            if (! array_key_exists($token, $values) || filled($values[$token])) {
                continue;
            }

            $used[$token] = true;
        }

        return array_keys($used);
    }

    /**
     * @return array<string, string>
     */
    public function values(EmailTemplateContext $context): array
    {
        $job = $context->job;

        return [
            'candidate.name' => (string) $context->candidate?->name,
            'candidate.email' => (string) $context->candidate?->email,
            'candidate.phone' => (string) $context->candidate?->phone,
            'job.title' => (string) $job?->name,
            'job.url' => $job === null
                ? ''
                : route('job.show', ['key' => $job->key]),
            'company.name' => (string) $context->company?->name,
            'application.status' => (string) $context->status?->name,
            'application.date' => $context->application?->created_at?->translatedFormat('d M Y') ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function substitute(?string $template, array $values, bool $escape): string
    {
        if (blank($template)) {
            return '';
        }

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
