<?php

use App\Ai\Agents\ScoreApplicationAgainstCriteria;
use App\Enums\ApplicationQuestionType;
use App\Enums\ApplicationSource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Services\CandidateEvaluationContextSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function sanitizerApplication(array $candidateAttributes = [], array $applicationAttributes = []): Application
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->create(['company_id' => $company->getKey()]);
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        ...$candidateAttributes,
    ]);

    return Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'candidate_id' => $candidate->getKey(),
        ...$applicationAttributes,
    ]);
}

test('direct candidate identifiers never reach the evaluation context', function (): void {
    $application = sanitizerApplication([
        'name' => 'Ada Lovelace',
        'email' => 'ada.lovelace@example.com',
        'phone' => '+5511987654321',
        'socials' => [
            ['network' => 'linkedin', 'account' => 'https://www.linkedin.com/in/ada-lovelace'],
            ['network' => 'other', 'account' => '@adacodes'],
        ],
    ], [
        'cover_letter_text' => '<p>My name is Ada Lovelace. Reach me at ada.lovelace@example.com '
            .'or +55 11 98765-4321, or on https://www.linkedin.com/in/ada-lovelace.</p>',
    ]);

    $application->answers()->create([
        'company_id' => $application->company_id,
        'question_snapshot' => 'What name should we use when contacting you?',
        'response_type' => ApplicationQuestionType::Text,
        'value_text' => 'Ada — you can also find me as @adacodes or at hello@ada.dev.',
    ]);

    $context = app(CandidateEvaluationContextSanitizer::class)->sanitize(
        $application->fresh(['candidate', 'answers']),
        'ADA LOVELACE — Senior Engineer. ada.lovelace@example.com | +55 (11) 98765-4321 | github.com/adacodes',
    );

    $payload = $context->coverLetter.' '.$context->resumeText.' '.json_encode($context->answers);

    foreach ([
        'Ada',
        'Lovelace',
        'ada.lovelace@example.com',
        'hello@ada.dev',
        '987654321',
        '98765-4321',
        'linkedin.com/in/ada-lovelace',
        'adacodes',
    ] as $identifier) {
        expect($payload)->not->toContain($identifier);
    }

    expect($payload)
        ->toContain(CandidateEvaluationContextSanitizer::NamePlaceholder)
        ->toContain(CandidateEvaluationContextSanitizer::EmailPlaceholder)
        ->and($context->redactionCount)->toBeGreaterThan(0);
});

test('professional evidence survives sanitization', function (): void {
    $application = sanitizerApplication([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+5511987654321',
    ], [
        'cover_letter_text' => '<p>At Nubank (2019-2023) I built and maintained a Laravel 11 payments API '
            .'handling 1.2M requests/month, and I hold an AWS Solutions Architect certification. '
            .'Revenue impact was R$ 10.000.000 across the programme.</p>',
    ]);

    $context = app(CandidateEvaluationContextSanitizer::class)->sanitize(
        $application->fresh(['candidate', 'answers']),
        'MSc Computer Science, University of São Paulo. Led migration of 47 services to Kubernetes.',
    );

    $payload = $context->coverLetter.' '.$context->resumeText;

    foreach ([
        'Nubank',
        '2019-2023',
        'Laravel 11',
        '1.2M requests/month',
        'AWS Solutions Architect',
        // A thousands-grouped metric must not be mistaken for a phone number.
        '10.000.000',
        'University of São Paulo',
        '47 services',
        'Kubernetes',
    ] as $evidence) {
        expect($payload)->toContain($evidence);
    }
});

test('referral sourcing metadata is never part of the evaluation context', function (): void {
    $application = sanitizerApplication([], [
        'cover_letter_text' => '<p>I have shipped Laravel APIs for six years.</p>',
    ]);

    $context = app(CandidateEvaluationContextSanitizer::class)->sanitize($application, null);

    expect(json_encode($context))
        ->not->toContain('referral')
        ->not->toContain('source');
});

test('the agent context carries criterion ids and no candidate identity', function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withConfirmedCriteria([
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
    ])->create(['company_id' => $company->getKey()]);
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+5511987654321',
    ]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'candidate_id' => $candidate->getKey(),
        'cover_letter_text' => '<p>I built Laravel payment APIs at Nubank.</p>',
        'source' => ApplicationSource::Referral,
    ]);

    $criterionId = (int) $job->jobCriteria()->sole()->getKey();
    $agent = new ScoreApplicationAgainstCriteria($application);
    $context = $agent->applicationContext(
        app(CandidateEvaluationContextSanitizer::class)->sanitize($application, null),
    );

    expect($context)
        ->toContain('criterion_id')
        ->toContain((string) $criterionId)
        ->toContain('Nubank')
        // Identity and sourcing metadata are simply not in the payload.
        ->not->toContain('candidate_name')
        ->not->toContain('Ada')
        ->not->toContain('Lovelace')
        ->not->toContain('ada@example.com')
        ->not->toContain('referral');
});
