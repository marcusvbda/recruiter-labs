<?php

namespace App\Services;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Data\BlindCandidateContext;
use App\Enums\ApplicationQuestionType;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Services\Concerns\RedactsCandidateIdentifiers;

/**
 * Removes direct candidate identifiers from the material that is about to be
 * sent to the candidate-evaluation agent.
 *
 * Candidate identity is not relevant to fit, so it is not sent. This is
 * deterministic Laravel code on purpose: no model call decides what is an
 * identifier, and the rules can be read, tested and disagreed with.
 *
 * The redaction rules themselves — what counts as an identifier, what is
 * deliberately kept as evidence, and what this is explicitly not — live in
 * {@see RedactsCandidateIdentifiers}, shared with the candidate-level sourcing
 * sanitizer so both remove exactly the same things.
 */
class CandidateEvaluationContextSanitizer
{
    use BuildsCompactAgentContext;
    use RedactsCandidateIdentifiers;

    public function sanitize(Application $application, ?string $resumeText): BlindCandidateContext
    {
        $application->loadMissing(['candidate', 'answers']);

        $patterns = $this->identifierPatternsFor($application->candidate);
        $redactions = 0;

        $coverLetter = $this->redactIdentifiers($this->plainText($application->cover_letter_text), $patterns, $redactions);
        $resume = $this->redactIdentifiers($resumeText, $patterns, $redactions);

        $answers = $application->answers
            ->map(function (ApplicationAnswer $answer) use ($patterns, &$redactions): array {
                $value = $answer->response_type === ApplicationQuestionType::Number
                    ? ($answer->value_number === null ? null : (string) $answer->value_number)
                    : $answer->value_text;

                return [
                    'question' => (string) $this->redactIdentifiers($answer->question_snapshot, $patterns, $redactions),
                    'answer' => $this->redactIdentifiers($value, $patterns, $redactions),
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
}
