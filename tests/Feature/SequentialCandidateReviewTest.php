<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Three applications in the same non-terminal stage with distinct
 * `status_entered_at` values, created out of order so the expected queue order
 * is not just insertion order.
 *
 * @return array{0: Company, 1: Job, 2: Status, 3: array{first: Application, second: Application, third: Application}}
 */
function sequentialReviewFixture(): array
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);

    $stage = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', false)
        ->orderBy('order')
        ->orderBy('id')
        ->firstOrFail();

    $make = fn (string $enteredAt, array $attributes = []): Application => Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $stage->getKey(),
        'status_entered_at' => $enteredAt,
        ...$attributes,
    ]);

    // Created third-oldest first on purpose: insertion order != queue order.
    $third = $make('2026-03-03 10:00:00');
    $first = $make('2026-03-01 10:00:00');
    $second = $make('2026-03-02 10:00:00');

    actAsCompany($company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($company, isQuiet: true);
    Filament::bootCurrentPanel();

    return [$company, $job, $stage, compact('first', 'second', 'third')];
}

/**
 * Opens the Application page (optionally with a Pipeline column context) and
 * returns the URLs of the sequential-review header actions.
 *
 * @return array{previous: string|null, skip: string|null, move: bool}
 */
function reviewUrlsFor(Application $application, ?int $pipelineStage): array
{
    $component = Livewire::withQueryParams($pipelineStage === null ? [] : ['pipelineStage' => $pipelineStage])
        ->test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertSuccessful();

    $page = $component->instance();
    $actions = collect((new ReflectionMethod($page, 'getHeaderActions'))->invoke($page))
        ->flatMap(fn (mixed $action): array => method_exists($action, 'getActions') ? $action->getActions() : [$action])
        ->keyBy(fn (mixed $action): string => $action->getName());

    return [
        'previous' => $actions->get('reviewQueuePrevious')?->getUrl(),
        'skip' => $actions->get('reviewQueueSkip')?->getUrl(),
        'move' => $actions->has('moveStatusAndReviewNext'),
    ];
}

function reviewUrl(Application $application, ?int $pipelineStage): string
{
    $parameters = ['record' => $application->getKey()];

    if ($pipelineStage !== null) {
        $parameters['pipelineStage'] = $pipelineStage;
    }

    return ApplicationResource::getUrl('view', $parameters, tenant: $application->company);
}

test('previous and skip follow the stage order and keep the pipeline stage context', function (): void {
    [, , $stage, $apps] = sequentialReviewFixture();
    $stageId = (int) $stage->getKey();

    $first = reviewUrlsFor($apps['first'], $stageId);
    $second = reviewUrlsFor($apps['second'], $stageId);
    $third = reviewUrlsFor($apps['third'], $stageId);

    expect($first['previous'])->toBeNull()
        ->and($first['skip'])->toBe(reviewUrl($apps['second'], $stageId))
        ->and($second['previous'])->toBe(reviewUrl($apps['first'], $stageId))
        ->and($second['skip'])->toBe(reviewUrl($apps['third'], $stageId))
        ->and($third['previous'])->toBe(reviewUrl($apps['second'], $stageId))
        ->and($third['skip'])->toBeNull()
        ->and($second['skip'])->toContain('pipelineStage='.$stageId)
        ->and($first['move'])->toBeTrue()
        ->and($third['move'])->toBeFalse();
});

test('the queue order ignores fit scores', function (): void {
    [, , $stage, $apps] = sequentialReviewFixture();

    // Scores sort exactly opposite to the entered-at order.
    $apps['first']->forceFill(['analysis_status' => ApplicationAnalysisStatus::Completed, 'analysis_score' => 10])->saveQuietly();
    $apps['second']->forceFill(['analysis_status' => ApplicationAnalysisStatus::Completed, 'analysis_score' => 50])->saveQuietly();
    $apps['third']->forceFill(['analysis_status' => ApplicationAnalysisStatus::Completed, 'analysis_score' => 95])->saveQuietly();

    $stageId = (int) $stage->getKey();

    expect(reviewUrlsFor($apps['first'], $stageId)['skip'])->toBe(reviewUrl($apps['second'], $stageId))
        ->and(reviewUrlsFor($apps['second'], $stageId)['skip'])->toBe(reviewUrl($apps['third'], $stageId))
        ->and(reviewUrlsFor($apps['third'], $stageId)['previous'])->toBe(reviewUrl($apps['second'], $stageId));
});

test('following skip or previous does not change the neighbouring application', function (): void {
    [, , $stage, $apps] = sequentialReviewFixture();
    $stageId = (int) $stage->getKey();

    $neighbour = $apps['second'];
    $neighbour->forceFill(['analysis_status' => ApplicationAnalysisStatus::Completed, 'analysis_score' => 70])->saveQuietly();
    $before = Application::query()->findOrFail($neighbour->getKey())->only([
        'status_id', 'status_entered_at', 'analysis_status', 'analysis_score', 'updated_at',
    ]);

    $this->travel(5)->minutes();

    // Opening the neighbour from either side is pure navigation.
    reviewUrlsFor($apps['first'], $stageId);
    reviewUrlsFor($apps['third'], $stageId);
    reviewUrlsFor($apps['second'], $stageId);

    $after = Application::query()->findOrFail($neighbour->getKey())->only([
        'status_id', 'status_entered_at', 'analysis_status', 'analysis_score', 'updated_at',
    ]);

    expect($after)->toEqual($before);
});

test('without a pipeline stage the page falls back to the job-wide order', function (): void {
    [$company, $job, $stage, $apps] = sequentialReviewFixture();

    $first = reviewUrlsFor($apps['first'], null);
    $third = reviewUrlsFor($apps['third'], null);

    expect($first['previous'])->toBeNull()
        ->and($first['skip'])->toBe(reviewUrl($apps['second'], null))
        ->and($first['skip'])->not->toContain('pipelineStage')
        ->and($third['skip'])->toBeNull();

    $lonely = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => Job::factory()->create(['company_id' => $company->getKey()])->getKey(),
    ]);

    $urls = reviewUrlsFor($lonely, null);

    expect($urls['previous'])->toBeNull()
        ->and($urls['skip'])->toBeNull();
});

test('a pipeline stage from another company never leaks into the queue', function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $otherCompany = Company::factory()->create();
    $otherJob = Job::factory()->create(['company_id' => $otherCompany->getKey()]);
    $foreignStatus = Status::query()->where('pipeline_id', $otherJob->pipeline_id)->orderBy('order')->firstOrFail();
    // Provisioned before the tenant is booted so company creation is unaffected by it.
    $foreignApplication = Application::factory()->create([
        'company_id' => $otherCompany->getKey(),
        'job_id' => $otherJob->getKey(),
        'status_id' => $foreignStatus->getKey(),
        'status_entered_at' => '2026-03-02 12:00:00',
    ]);

    [, , , $apps] = sequentialReviewFixture();

    // The foreign context is ignored: the job-wide order applies and only this
    // job's applications are ever offered.
    $foreignId = (int) $foreignStatus->getKey();
    $urls = reviewUrlsFor($apps['second'], $foreignId);

    expect($urls['previous'])->toContain('/applications/'.$apps['first']->getKey())
        ->and($urls['skip'])->toContain('/applications/'.$apps['third']->getKey())
        ->and($urls['previous'].$urls['skip'])->not->toContain('/applications/'.$foreignApplication->getKey());
});

test('move and review next moves the application and continues to the one that was next', function (): void {
    [, $job, $stage, $apps] = sequentialReviewFixture();
    $stageId = (int) $stage->getKey();

    $target = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', false)
        ->where('is_final_stage', false)
        ->whereKeyNot($stage->getKey())
        ->orderBy('order')
        ->firstOrFail();

    Livewire::withQueryParams(['pipelineStage' => $stageId])
        ->test(ViewApplication::class, ['record' => $apps['first']->getKey()])
        ->callAction('moveStatusAndReviewNext', ['status_id' => $target->getKey()])
        ->assertRedirect(reviewUrl($apps['second'], $stageId));

    expect($apps['first']->refresh()->status_id)->toBe($target->getKey());
});
