<?php

namespace App\Services;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Data\BlindCandidateSourcingContext;
use App\Data\CandidateSourcingMaterial;
use App\Models\Candidate;
use App\Services\Concerns\RedactsCandidateIdentifiers;

/**
 * Removes direct candidate identifiers from the aggregated material that is
 * about to be sent to the candidate-sourcing agent.
 *
 * The candidate-level counterpart of {@see CandidateEvaluationContextSanitizer}:
 * that one sanitizes a single application, this one sanitizes everything a
 * candidate submitted across their history before they are considered for a job
 * they did not apply to. Both share {@see RedactsCandidateIdentifiers}, so what
 * counts as an identifier is defined once and cannot drift apart.
 *
 * The identifiers redacted here are the ones the workspace stores on the
 * {@see Candidate} — name, email, phone, social profiles — plus any email
 * address, unambiguous phone shape and known personal-profile URL found in the
 * text itself.
 *
 * **Structured interview feedback is not an input and cannot become one.** The
 * signature accepts {@see CandidateSourcingMaterial} items only, whose source is
 * a `CriterionEvidenceSource` — resume, cover letter or application answer.
 * Interview feedback, recruiter notes and previous AI evaluations are not
 * candidate-submitted material and have no representation in this type.
 *
 * **What this is not.** Not anonymisation, not bias elimination, no legal
 * guarantee. Copy may say only that direct candidate identifiers are excluded
 * from the AI context; candidate identity stays fully visible to the human
 * recruiter everywhere else.
 */
class CandidateSourcingContextSanitizer
{
    use BuildsCompactAgentContext;
    use RedactsCandidateIdentifiers;

    /**
     * @param  iterable<CandidateSourcingMaterial>  $materials
     */
    public function sanitize(Candidate $candidate, iterable $materials): BlindCandidateSourcingContext
    {
        $patterns = $this->identifierPatternsFor($candidate);
        $redactions = 0;
        $sanitized = [];

        foreach ($materials as $material) {
            $text = $this->redactIdentifiers($this->plainText($material->text), $patterns, $redactions);

            // Material that is nothing but identifiers leaves nothing to
            // evaluate, so it is dropped rather than sent as an empty item the
            // agent would have to reason about.
            if ($text === null) {
                continue;
            }

            $sanitized[] = $material->withText(
                $text,
                $this->redactIdentifiers($this->plainText($material->label), $patterns, $redactions),
            );
        }

        return new BlindCandidateSourcingContext(
            materials: $sanitized,
            redactionCount: $redactions,
        );
    }
}
