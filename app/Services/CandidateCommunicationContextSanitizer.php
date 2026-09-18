<?php

namespace App\Services;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Models\Candidate;
use App\Services\Concerns\RedactsCandidateIdentifiers;

/**
 * Sanitizes recruiter-authored historical outbound text before it can be used
 * as follow-up context. Those messages are useful context, but may contain the
 * candidate's name or contact details and must not become an identity channel.
 */
class CandidateCommunicationContextSanitizer
{
    use BuildsCompactAgentContext;
    use RedactsCandidateIdentifiers;

    public function sanitizeOutboundText(Candidate $candidate, ?string $text): ?string
    {
        $redactions = 0;

        return $this->redactIdentifiers(
            $this->plainText($text),
            $this->identifierPatternsFor($candidate),
            $redactions,
        );
    }
}
