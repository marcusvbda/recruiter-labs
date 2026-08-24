<?php

use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the pipeline board orders by time waiting in the stage, never by AI fit', function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);
    $status = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', false)
        ->orderBy('order')
        ->firstOrFail();

    // The strongest fit is also the newest arrival, so an ordering that leads
    // with `analysis_score` and one that leads with waiting time disagree.
    $strongAndRecent = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $status->getKey(),
        'analysis_score' => 95,
        'status_entered_at' => CarbonImmutable::now()->subDay(),
    ]);
    $weakAndWaiting = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $status->getKey(),
        'analysis_score' => 20,
        'status_entered_at' => CarbonImmutable::now()->subDays(12),
    ]);

    $widget = new JobPipelineKanban;
    $widget->record = $job;

    $ordering = new ReflectionMethod($widget, 'applyCardOrdering');
    $query = new ReflectionMethod($widget, 'getQuery');

    $ordered = $ordering->invoke($widget, $query->invoke($widget))->get();

    expect($ordered->pluck('id')->all())->toBe([
        (int) $weakAndWaiting->getKey(),
        (int) $strongAndRecent->getKey(),
    ]);
});

test('the board query does not order by the AI fit score at all', function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->create(['company_id' => $company->getKey()]);

    $widget = new JobPipelineKanban;
    $widget->record = $job;

    $sql = (new ReflectionMethod($widget, 'applyCardOrdering'))
        ->invoke($widget, (new ReflectionMethod($widget, 'getQuery'))->invoke($widget))
        ->toSql();

    expect($sql)->not->toContain('analysis_score')
        ->and($sql)->toContain('status_entered_at');
});
