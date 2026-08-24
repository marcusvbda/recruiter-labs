<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The next-action rule and the header's action set are private to the page, so
 * they are exercised through reflection rather than by rendering Livewire: what
 * is being locked here is the recruiting semantics, not the markup.
 */
function nextActionKeyFor(Application $application): string
{
    $page = new ViewApplication;
    $method = new ReflectionMethod($page, 'nextActionKey');

    return $method->invoke($page, $application, null);
}

function nextActionCompany(): Company
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    return Company::factory()->create();
}

function applicationInStatus(Company $company, callable $pickStatus, array $attributes = []): Application
{
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);

    $status = $pickStatus(Status::query()->where('pipeline_id', $job->pipeline_id));

    return Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $status->getKey(),
        ...$attributes,
    ])->load(['status', 'job']);
}

test('an early workflow stage with no interview does not suggest scheduling one', function (): void {
    $application = applicationInStatus(
        nextActionCompany(),
        fn ($statuses) => $statuses->where('is_terminal', false)->orderBy('order')->firstOrFail(),
        ['analysis_status' => ApplicationAnalysisStatus::Completed],
    );

    expect(nextActionKeyFor($application))->toBe('review_candidate');
});

test('an application waiting for criteria says so instead of waiting for the evaluation', function (): void {
    $application = applicationInStatus(
        nextActionCompany(),
        fn ($statuses) => $statuses->where('is_terminal', false)->orderBy('order')->firstOrFail(),
        ['analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria],
    );

    expect(nextActionKeyFor($application))->toBe('awaiting_criteria');
});

test('a terminal application offers no recruiting next step', function (): void {
    $company = nextActionCompany();

    $rejected = applicationInStatus(
        $company,
        fn ($statuses) => $statuses->where('is_terminal', true)->where('is_hired', false)->firstOrFail(),
        ['analysis_status' => ApplicationAnalysisStatus::Completed],
    );
    $hired = applicationInStatus(
        $company,
        fn ($statuses) => $statuses->where('is_hired', true)->firstOrFail(),
        ['analysis_status' => ApplicationAnalysisStatus::Completed],
    );

    expect(nextActionKeyFor($rejected))->toBe('closed')
        ->and(nextActionKeyFor($hired))->toBe('hired');

    $page = new ViewApplication;
    $actions = new ReflectionMethod($page, 'nextActionActions');

    expect($actions->invoke($page, $rejected, 'closed'))->toBe([])
        ->and($actions->invoke($page, $hired, 'hired'))->toBe([]);
});

test('a terminal application never offers interview scheduling, not even in the overflow', function (): void {
    $application = applicationInStatus(
        nextActionCompany(),
        fn ($statuses) => $statuses->where('is_terminal', true)->where('is_hired', false)->firstOrFail(),
    );

    // Building Filament actions resolves resource URLs, which needs a booted
    // panel, a signed-in user and an active tenant.
    actAsCompany($application->company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($application->company, isQuiet: true);
    Filament::bootCurrentPanel();

    $page = new ViewApplication;
    (new ReflectionProperty($page, 'record'))->setValue($page, $application);

    $names = collect((new ReflectionMethod($page, 'getHeaderActions'))->invoke($page))
        ->flatMap(fn (mixed $action): array => method_exists($action, 'getActions')
            ? array_map(fn (mixed $nested): string => $nested->getName(), $action->getActions())
            : [$action->getName()])
        ->all();

    expect($names)->not->toContain('scheduleInterview')
        ->and($names)->toContain('moveStatus');
});
