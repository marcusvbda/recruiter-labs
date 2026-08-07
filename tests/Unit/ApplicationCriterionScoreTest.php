<?php

use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('belongs to an application and casts weight and score to integers', function () {
    $application = Application::factory()->create();

    $score = ApplicationCriterionScore::query()->create([
        'company_id' => $application->company_id,
        'application_id' => $application->id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'score' => 78,
        'reason' => 'Strong backend experience across several roles.',
    ]);

    expect($application->criterionScores()->sole()->is($score))->toBeTrue()
        ->and($score->weight)->toBe(9)
        ->and($score->score)->toBe(78);
});
