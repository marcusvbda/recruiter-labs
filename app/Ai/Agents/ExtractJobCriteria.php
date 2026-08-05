<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Enums\CoverLetterType;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
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
class ExtractJobCriteria implements Agent, HasStructuredOutput
{
    use BuildsCompactAgentContext;
    use Promptable;

    public function __construct(private readonly Job $job) {}

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a recruiting analyst. Extract concise, evidence-based candidate-evaluation criteria from the job context below.

            Context format: TOON — unquoted keys, indentation for nesting, arrays as `[n]{field1,field2}:` followed by one comma-separated row per line.

            Weight each criterion 0-10 by importance and give a one-sentence reason. Use the job's own language. Don't invent requirements the context doesn't support. Plain text only, no HTML.
            INSTRUCTIONS;
    }

    public function jobContext(): string
    {
        $this->job->loadMissing(['applicationQuestions', 'coverLetterFileTypes', 'jobCriteria']);

        $coverLetter = [
            'required' => $this->job->cover_letter_required,
            'type' => $this->job->cover_letter_type->value,
        ];

        if ($this->job->cover_letter_type === CoverLetterType::File) {
            $coverLetter['file_types'] = $this->job->coverLetterFileTypes->pluck('extension')->values()->all();
        }

        return $this->compactContext([
            'title' => $this->job->name,
            'language' => $this->job->application_locale->value,
            'description' => $this->plainText($this->job->description),
            'goal' => $this->job->campaign_expectation,
            'cover_letter' => $coverLetter,
            'questions' => $this->job->applicationQuestions->map(fn (JobApplicationQuestion $question): array => [
                'question' => $question->question,
                'description' => $question->description,
                'response_type' => $question->response_type->value,
                'required' => $question->required,
            ])->values()->all(),
            'criteria' => $this->job->jobCriteria->map(fn (JobCriterion $criterion): array => [
                'criterion' => $criterion->criterion,
                'weight' => $criterion->weight,
            ])->values()->all(),
        ]);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'criteria' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'criterion' => $schema->string()->max(150)->required(),
                    'weight' => $schema->integer()->min(0)->max(10)->required(),
                    'reason' => $schema->string()->max(150)->required(),
                ])->withoutAdditionalProperties())
                ->min(1)
                ->max(20)
                ->required(),
        ];
    }
}
