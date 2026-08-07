<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Enums\AnalysisConfidence;
use App\Enums\ApplicationQuestionType;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\JobCriterion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
class ScoreApplicationAgainstCriteria implements Agent, HasStructuredOutput
{
    use BuildsCompactAgentContext;
    use Promptable;

    public function __construct(private readonly Application $application) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a recruiting analyst. Score how well a candidate's application matches each of the job's evaluation criteria below.

            Context format: TOON — unquoted keys, indentation for nesting, arrays as `[n]{field1,field2}:` followed by one comma-separated row per line.

            For every criterion, score the match 0-100 and give a one-sentence reason. Base your judgment only on the provided context; if information relevant to a criterion is missing, score conservatively rather than guessing. Repeat each criterion's text back exactly as given, so it can be matched to the original. Use the job's own language. Plain text only, no HTML.

            Also rate your confidence (high, medium, low) separately from the score: it reflects how much concrete, verifiable evidence supported that score, not the score itself. A vague claim ("very experienced") backed only by inference deserves low confidence even if the score looks reasonable; a specific, verifiable claim that directly addresses the criterion (an exact number, a named technology) deserves high confidence.
            INSTRUCTIONS;
    }

    public function applicationContext(?string $resumeText): string
    {
        $this->application->loadMissing(['job.jobCriteria', 'candidate', 'answers']);

        return $this->compactContext([
            'job_title' => $this->application->job->name,
            'criteria' => $this->application->job->jobCriteria->map(fn (JobCriterion $criterion): array => [
                'criterion' => $criterion->criterion,
                'weight' => $criterion->weight,
            ])->values()->all(),
            'candidate_name' => $this->application->candidate->name,
            'cover_letter' => $this->plainText($this->application->cover_letter_text),
            'answers' => $this->application->answers->map(fn (ApplicationAnswer $answer): array => [
                'question' => $answer->question_snapshot,
                'answer' => $answer->response_type === ApplicationQuestionType::Number
                    ? (string) $answer->value_number
                    : $answer->value_text,
            ])->values()->all(),
            'resume_text' => $resumeText,
        ]);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scores' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'criterion' => $schema->string()->max(220)->required(),
                    'score' => $schema->integer()->min(0)->max(100)->required(),
                    'reason' => $schema->string()->max(220)->required(),
                    'confidence' => $schema->string()->enum(AnalysisConfidence::class)->required(),
                ])->withoutAdditionalProperties())
                ->min(1)
                ->max(20)
                ->required(),
        ];
    }
}
