<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Str;

/**
 * The one place a recruiter-authored message body becomes trusted HTML.
 *
 * A composer body arrives as client-controlled Livewire state — a rich-text
 * document while the editor is open, a raw string once it is submitted — and
 * ends up in an immutable authorized snapshot that is emailed to a candidate.
 * It is therefore sanitized here, before it is stored, and again where it is
 * rendered, so a row written before this composer existed (or by any other
 * caller) can never reach a candidate's inbox as executable markup.
 *
 * Plain text keeps its line breaks and its literal characters: a legacy draft
 * written as text must read the same after the composer reset.
 */
final class CandidateMessageBody
{
    /**
     * @param  string|array<mixed>|null  $body
     */
    public static function toStoredHtml(string|array|null $body): string
    {
        if (is_array($body)) {
            // RichContentRenderer::toHtml() sanitizes the editor document.
            return RichContentRenderer::make($body)->toHtml();
        }

        if (! is_string($body) || $body === '') {
            return '';
        }

        // Detected by an actual tag rather than by `strip_tags()`: a legacy
        // plain-text message may contain "<3", which `strip_tags()` eats and
        // would misclassify as markup.
        if (preg_match('/<(?:[a-z][a-z0-9-]*)(?:\s[^<>]*)?\/?>/i', $body) !== 1) {
            return nl2br(e($body));
        }

        return Str::sanitizeHtml($body);
    }
}
