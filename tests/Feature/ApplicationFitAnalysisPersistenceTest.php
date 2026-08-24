<?php

use App\Actions\ReplaceApplicationFitAnalysis;
use App\Enums\AnalysisConfidence;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationSource;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Models\Plan;
use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A job with three weighted criteria and one application queued against them —
 * the shape every fit/coverage assertion below reasons about.
 *
 * @return array{0: Application, 1: array<string, JobCriterion>}
 */
function evaluationFixture(array $weights = ['Laravel' => 10, 'AWS' => 8, 'Leadership' => 6]): array
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withConfirmedCriteria(
        array_map(
            fn (int $weight, string $criterion): array => ['criterion' => $criterion, 'weight' => $weight],
            array_values($weights),
            array_keys($weights),
        ),
    )->create(['company_id' => $company->getKey()]);

    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Pending,
        'analysis_generation' => 1,
    ]);

    return [$application, $job->jobCriteria()->get()->keyBy('criterion')->all()];
}

/** @param  array<string, JobCriterion>  $criteria */
function scoreRow(array $criteria, string $criterion, ?int $score, string $confidence, array $evidence = []): array
{
    return [
        'criterion_id' => (int) $criteria[$criterion]->getKey(),
        'score' => $score,
        'reason' => $score === null
            ? 'The application says nothing about this.'
            : 'The application describes concrete work here.',
        'confidence' => $confidence,
        'evidence' => $evidence,
    ];
}

test('an unknown criterion is persisted as null and excluded from overall fit', function (): void {
    [$application, $criteria] = evaluationFixture();

    $replaced = app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high', [
            ['source' => 'resume', 'detail' => 'Laravel 11 payments API, 1.2M requests/month.'],
        ]),
        scoreRow($criteria, 'AWS', 70, 'medium'),
        scoreRow($criteria, 'Leadership', null, 'low'),
    ], [], 1);

    $application->refresh();

    expect($replaced)->toBeTrue()
        ->and($application->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        // (90*10 + 70*8) / 18 — Leadership is in neither numerator nor denominator.
        ->and((float) $application->analysis_score)->toBe(81.11)
        // 18 of 24 weight could be assessed.
        ->and($application->analysis_coverage)->toBe(75);

    $leadership = $application->criterionScores->firstWhere('criterion', 'Leadership');

    expect($leadership->score)->toBeNull()
        ->and($leadership->isAssessed())->toBeFalse()
        ->and($leadership->evidence)->toBeNull();
});

test('a null score does not lower overall fit', function (): void {
    [$withUnknown, $criteria] = evaluationFixture();

    app(ReplaceApplicationFitAnalysis::class)->handle($withUnknown, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
        scoreRow($criteria, 'AWS', 70, 'medium'),
        scoreRow($criteria, 'Leadership', null, 'low'),
    ], [], 1);

    [$assessedOnly, $otherCriteria] = evaluationFixture(['Laravel' => 10, 'AWS' => 8]);

    app(ReplaceApplicationFitAnalysis::class)->handle($assessedOnly, [
        scoreRow($otherCriteria, 'Laravel', 90, 'high'),
        scoreRow($otherCriteria, 'AWS', 70, 'medium'),
    ], [], 1);

    expect((float) $withUnknown->refresh()->analysis_score)
        ->toBe((float) $assessedOnly->refresh()->analysis_score);
});

test('evidence coverage falls as important criteria go unassessed', function (): void {
    [$full, $fullCriteria] = evaluationFixture();

    app(ReplaceApplicationFitAnalysis::class)->handle($full, [
        scoreRow($fullCriteria, 'Laravel', 90, 'high'),
        scoreRow($fullCriteria, 'AWS', 70, 'medium'),
        scoreRow($fullCriteria, 'Leadership', 55, 'medium'),
    ], [], 1);

    [$sparse, $sparseCriteria] = evaluationFixture();

    app(ReplaceApplicationFitAnalysis::class)->handle($sparse, [
        scoreRow($sparseCriteria, 'Laravel', 90, 'high'),
        scoreRow($sparseCriteria, 'AWS', null, 'low'),
        scoreRow($sparseCriteria, 'Leadership', null, 'low'),
    ], [], 1);

    expect($full->refresh()->analysis_coverage)->toBe(100)
        ->and($sparse->refresh()->analysis_coverage)->toBe(42)
        // Fit stays high: nothing suggests the candidate is weak on what is unknown.
        ->and((int) round((float) $sparse->analysis_score))->toBe(90);
});

test('high confidence is normalised down when the criterion could not be assessed', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10]);

    app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', null, 'high', [
            ['source' => 'resume', 'detail' => 'Nothing relevant, but claimed high confidence.'],
        ]),
    ], [], 1);

    $score = $application->refresh()->criterionScores->sole();

    expect($score->confidence)->toBe(AnalysisConfidence::Low)
        ->and($score->score)->toBeNull()
        ->and($score->evidence)->toBeNull()
        ->and($application->analysis_score)->toBeNull()
        ->and($application->analysis_coverage)->toBe(0);
});

test('criterion text and weight are read from the job, never from the response', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10]);

    app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        [
            'criterion_id' => (int) $criteria['Laravel']->getKey(),
            'score' => 80,
            'reason' => 'Concrete Laravel work.',
            'confidence' => 'high',
            'evidence' => [['source' => 'cover_letter', 'detail' => 'Built a Laravel payments API.']],
        ],
    ], [], 1);

    $score = $application->refresh()->criterionScores->sole();

    expect($score->criterion)->toBe('Laravel')
        ->and($score->weight)->toBe(10)
        ->and($score->evidence)->toBe([['source' => 'cover_letter', 'detail' => 'Built a Laravel payments API.']]);
});

test('a response missing a criterion fails instead of scoring what it returned', function (): void {
    [$application, $criteria] = evaluationFixture();

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
        scoreRow($criteria, 'AWS', 70, 'medium'),
    ], [], 1))->toThrow(ValidationException::class);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->criterionScores)->toHaveCount(0);
});

test('a duplicated criterion fails instead of being scored twice', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10, 'AWS' => 8]);

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
        scoreRow($criteria, 'Laravel', 40, 'low'),
        scoreRow($criteria, 'AWS', 70, 'medium'),
    ], [], 1))->toThrow(ValidationException::class);
});

test('an unknown criterion id fails instead of receiving a fallback weight', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10]);

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
        [
            'criterion_id' => 999_999,
            'score' => 50,
            'reason' => 'A criterion this job does not have.',
            'confidence' => 'medium',
            'evidence' => [],
        ],
    ], [], 1))->toThrow(ValidationException::class);

    expect(ApplicationCriterionScore::query()->count())->toBe(0);
});

test('a criterion belonging to another company cannot be scored', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10]);

    $otherCompany = Company::factory()->create();
    $otherJob = Job::factory()->withConfirmedCriteria([['criterion' => 'Laravel', 'weight' => 10]])
        ->create(['company_id' => $otherCompany->getKey()]);

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
        [
            'criterion_id' => (int) $otherJob->jobCriteria()->sole()->getKey(),
            'score' => 50,
            'reason' => 'Another tenant\'s criterion.',
            'confidence' => 'medium',
            'evidence' => [],
        ],
    ], [], 1))->toThrow(ValidationException::class);
});

test('interview brief items reference criteria strictly by id', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10, 'Leadership' => 6]);

    app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
        scoreRow($criteria, 'Leadership', null, 'low'),
    ], [
        [
            'criterion_id' => (int) $criteria['Leadership']->getKey(),
            'priority' => 'high',
            'reason' => 'Nothing in the application speaks to team leadership.',
            'question' => 'Tell me about the largest team you have been responsible for.',
        ],
    ], 1);

    $briefItem = $application->refresh()->interviewBriefItems->sole();

    expect($briefItem->criterion)->toBe('Leadership')
        ->and($briefItem->criterionScore->criterion)->toBe('Leadership')
        ->and($briefItem->criterionScore->score)->toBeNull();
});

test('an interview brief item referencing an unknown criterion fails', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 10]);

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 90, 'high'),
    ], [
        [
            'criterion_id' => 999_999,
            'priority' => 'high',
            'reason' => 'Refers to nothing.',
            'question' => 'Unanswerable.',
        ],
    ], 1))->toThrow(ValidationException::class);
});

test('referral sourcing does not change the evaluation result', function (): void {
    $scores = fn (array $criteria): array => [
        scoreRow($criteria, 'Laravel', 90, 'high', [
            ['source' => 'resume', 'detail' => 'Laravel 11 payments API.'],
        ]),
        scoreRow($criteria, 'AWS', null, 'low'),
    ];

    [$direct, $directCriteria] = evaluationFixture(['Laravel' => 10, 'AWS' => 8]);
    app(ReplaceApplicationFitAnalysis::class)->handle($direct, $scores($directCriteria), [], 1);

    [$referred, $referredCriteria] = evaluationFixture(['Laravel' => 10, 'AWS' => 8]);
    $referral = Referral::factory()->create([
        'company_id' => $referred->company_id,
        'job_id' => $referred->job_id,
    ]);
    $referred->forceFill([
        'source' => ApplicationSource::Referral,
        'referral_id' => $referral->getKey(),
    ])->saveQuietly();

    app(ReplaceApplicationFitAnalysis::class)->handle($referred, $scores($referredCriteria), [], 1);

    expect((float) $referred->refresh()->analysis_score)->toBe((float) $direct->refresh()->analysis_score)
        ->and($referred->analysis_coverage)->toBe($direct->analysis_coverage);
});

test('zero-weight criteria cannot distort evidence coverage', function (): void {
    [$application, $criteria] = evaluationFixture(['Laravel' => 0, 'AWS' => 0]);

    app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        scoreRow($criteria, 'Laravel', 80, 'high'),
        scoreRow($criteria, 'AWS', null, 'low'),
    ], [], 1);

    // With no weight to rank them, each criterion counts once.
    expect($application->refresh()->analysis_coverage)->toBe(50)
        ->and((float) $application->analysis_score)->toBe(80.0);
});
