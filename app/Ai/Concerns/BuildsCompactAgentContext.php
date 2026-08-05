<?php

namespace App\Ai\Concerns;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

/**
 * Shared helpers for agents that build a token-efficient TOON context payload
 * from application data (e.g. rich-text fields, sparse optional attributes).
 */
trait BuildsCompactAgentContext
{
    /**
     * Encode a context array as TOON, dropping empty optional values (null,
     * empty string, empty array) since they add tokens without adding meaning.
     */
    protected function compactContext(array $data): string
    {
        $filtered = array_filter(
            $data,
            fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );

        return Toon::encode($filtered, EncodeOptions::compact());
    }

    /**
     * Strip a rich-text editor's HTML markup, keeping block boundaries as plain
     * spaces so sentences don't run together. A space (not a line break) is used
     * deliberately: TOON must escape newlines inside quoted strings as a literal
     * `\n`, which costs tokens without adding meaning for the agent.
     */
    protected function plainText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $withSpacing = preg_replace('#</(p|li|h[1-6]|div|tr)>|<br\s*/?>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($withSpacing), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text)) ?: null;
    }
}
