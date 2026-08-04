<?php

namespace App\Ai\Agents;

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
#[Model('gpt-4.1-mini')]
class ExtractJobCriteria implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly Job $job) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are an expert recruiting analyst. Analyze every detail supplied about a job opening and identify the concise, evidence-based criteria that should later be used to evaluate candidates.

            Return only criteria that are relevant to candidate assessment. Assign each criterion an integer weight from 0 to 10 based on its importance. Explain why each criterion matters for this specific job. Both the criterion and reason must use the same language in which the job was written. Do not invent requirements that are not supported by the supplied job context.
            INSTRUCTIONS;
    }

    public function jobContext(): string
    {
        $this->job->loadMissing(['applicationQuestions', 'acceptedCvTypes', 'coverLetterFileTypes', 'jobCriteria']);

        return json_encode([
            'title' => $this->job->name,
            'language' => $this->job->application_locale->value,
            'description' => $this->job->description,
            'campaign_expectation' => $this->job->campaign_expectation,
            'campaign_starts_at' => $this->job->starts_at?->toDateString(),
            'campaign_ends_at' => $this->job->ends_at?->toDateString(),
            'application_limit' => $this->job->application_limit,
            'cover_letter' => [
                'required' => $this->job->cover_letter_required,
                'type' => $this->job->cover_letter_type->value,
                'accepted_file_types' => $this->job->coverLetterFileTypes->pluck('extension')->values()->all(),
            ],
            'accepted_cv_file_types' => $this->job->acceptedCvTypes->pluck('extension')->values()->all(),
            'custom_questions' => $this->job->applicationQuestions->map(fn (JobApplicationQuestion $question): array => [
                'question' => $question->question,
                'description' => $question->description,
                'response_type' => $question->response_type->value,
                'required' => $question->required,
            ])->values()->all(),
            'current_criteria' => $this->job->jobCriteria->map(fn (JobCriterion $criterion): array => [
                'criterion' => $criterion->criterion,
                'weight' => $criterion->weight,
                'reason' => $criterion->reason,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                    'reason' => $schema->string()->required(),
                ])->withoutAdditionalProperties())
                ->min(1)
                ->max(20)
                ->required(),
        ];
    }
}
