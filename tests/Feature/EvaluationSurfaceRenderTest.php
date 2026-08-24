<?php

use App\Actions\ConfirmJobCriteria;
use App\Actions\ReplaceApplicationFitAnalysis;
use App\Actions\RequireJobCriteriaReview;
use App\Enums\ApplicationAnalysisStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * These render the real Filament pages, because the evaluation copy is part of
 * the feature: a page that computes fit and coverage correctly but throws while
 * displaying them has not shipped anything.
 *
 * @return array{0: Company, 1: Job, 2: Application}
 */
function renderFixture(): array
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withCriteriaAwaitingReview([
        ['criterion' => 'Production Laravel experience', 'weight' => 10],
        ['criterion' => 'Led a team of 5+ engineers', 'weight' => 6],
    ])->create(['company_id' => $company->getKey()]);

    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
    ]);

    actAsCompany($company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($company, isQuiet: true);
    Filament::bootCurrentPanel();

    return [$company, $job, $application];
}

test('the criteria tab shows suggested criteria awaiting confirmation', function (): void {
    [$company, $job] = renderFixture();

    // The criteria tab's schema only renders once that tab is the active one.
    Livewire::test(EditJob::class, ['record' => $job->getKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertSee(__('jobs.criteria.awaiting_review.title'))
        ->assertSee(__('jobs.criteria.awaiting_review.badge'))
        ->assertSee(__('jobs.criteria.confirm_action'))
        ->assertDontSee(__('jobs.criteria.confirmed.governs'));
});

test('the evaluation tab shows fit, coverage, evidence and the identity disclosure', function (): void {
    [$company, $job, $application] = renderFixture();

    app(ConfirmJobCriteria::class)->handle($job);
    $criteria = $job->refresh()->jobCriteria()->get()->keyBy('criterion');

    $application->forceFill(['analysis_generation' => 1])->saveQuietly();

    app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        [
            'criterion_id' => (int) $criteria['Production Laravel experience']->getKey(),
            'score' => 90,
            'reason' => 'The application describes a production Laravel service in concrete terms.',
            'confidence' => 'high',
            'evidence' => [
                ['source' => 'resume', 'detail' => 'Laravel 11 payments API handling 1.2M requests/month.'],
            ],
        ],
        [
            'criterion_id' => (int) $criteria['Led a team of 5+ engineers']->getKey(),
            'score' => null,
            'reason' => 'Nothing in the application describes leading a team.',
            'confidence' => 'low',
            'evidence' => [],
        ],
    ], [
        [
            'criterion_id' => (int) $criteria['Led a team of 5+ engineers']->getKey(),
            'priority' => 'high',
            'reason' => 'Team leadership is important here and the application says nothing about it.',
            'question' => 'What is the largest engineering team you have been responsible for?',
        ],
    ], 1);

    $this->get(ApplicationResource::getUrl('view', [
        'record' => $application,
        'section' => 'evaluation',
    ], tenant: $company))
        ->assertOk()
        // Fit is 90 from the one assessable criterion; coverage is 10/16 weight.
        ->assertSee(__('applications.admin.ai.overall_score_label'))
        ->assertSee(__('applications.admin.ai.coverage_label'))
        ->assertSee('63')
        ->assertSee(__('applications.admin.ai.criteria.not_assessed'))
        ->assertSee(__('applications.admin.ai.evidence.heading'))
        ->assertSee('Laravel 11 payments API handling 1.2M requests/month.')
        ->assertSee(__('applications.admin.ai.scope_disclosure'))
        ->assertSee('What is the largest engineering team you have been responsible for?');
});

test('an application waiting for criteria says the criteria need confirming', function (): void {
    [$company, $job, $application] = renderFixture();

    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ])->saveQuietly();

    $this->get(ApplicationResource::getUrl('view', [
        'record' => $application,
        'section' => 'evaluation',
    ], tenant: $company))
        ->assertOk()
        ->assertSee(__('applications.admin.ai.states.awaiting_criteria.title'));
});

test('an evaluation measured against superseded criteria is not shown as current', function (): void {
    [$company, $job, $application] = renderFixture();

    app(ConfirmJobCriteria::class)->handle($job);

    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $job->refresh()->criteria_generation,
        'analysis_score' => 84,
        'analysis_coverage' => 72,
    ])->saveQuietly();

    app(RequireJobCriteriaReview::class)->handle($job);

    $this->get(ApplicationResource::getUrl('view', [
        'record' => $application->fresh(),
        'section' => 'evaluation',
    ], tenant: $company))
        ->assertOk()
        ->assertSee(__('applications.admin.ai.states.outdated.title'))
        // The superseded fit is not presented anywhere on the page.
        ->assertDontSee(__('applications.admin.summary.fit_score', ['score' => 84]));
});

test('a recruiter can confirm the criteria from the job workspace', function (): void {
    [, $job, $application] = renderFixture();

    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ])->saveQuietly();

    // Jobs have no policy in this application, so a policy-based gate would deny
    // every recruiter. The confirm action must use the same resource gate the
    // rest of the job surfaces use.
    expect(JobResource::canEdit($job))->toBeTrue();

    app(ConfirmJobCriteria::class)->handle($job, (int) auth()->id());

    expect($job->refresh()->hasConfirmedCriteria())->toBeTrue()
        ->and($job->criteria_confirmed_by_id)->toBe((int) auth()->id())
        ->and($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending);
});

test('the criteria confirmation next step is offered on a waiting application', function (): void {
    [$company, , $application] = renderFixture();

    $application->forceFill([
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ])->saveQuietly();

    $this->get(ApplicationResource::getUrl('view', [
        'record' => $application->fresh(),
        'section' => 'summary',
    ], tenant: $company))
        ->assertOk()
        ->assertSee(__('applications.admin.summary.next_actions.awaiting_criteria.title'))
        // The link into the job's criteria tab must actually render, not be
        // silently dropped by a gate that can never pass.
        ->assertSee(__('jobs.criteria.confirm_action'));
});

test('every navigable admin route has an active sidebar item', function (): void {
    renderFixture();

    $panel = Filament::getPanel('admin');
    $patterns = [];

    foreach ($panel->getResources() as $resource) {
        if (! $resource::shouldRegisterNavigation()) {
            continue;
        }

        foreach ((array) $resource::getNavigationItemActiveRoutePattern() as $pattern) {
            $patterns[] = $pattern;
        }
    }

    foreach ($panel->getPages() as $page) {
        if ($page::shouldRegisterNavigation()) {
            $patterns[] = $page::getRouteName();
        }
    }

    foreach ($panel->getClusters() as $cluster) {
        foreach ((array) $cluster::getNavigationItemActiveRoutePattern() as $pattern) {
            $patterns[] = $pattern;
        }
    }

    $orphans = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null || ! str_starts_with($name, 'filament.admin.')) {
            continue;
        }

        // Auth and tenant registration sit outside the sidebar by design.
        if (str_contains($name, '.auth.') || str_contains($name, '.tenant')) {
            continue;
        }

        $owned = collect($patterns)->contains(fn (string $pattern): bool => fnmatch($pattern, $name));

        if (! $owned) {
            $orphans[] = $name;
        }
    }

    // A page whose sidebar has nothing selected has lost the recruiter's place.
    // Registering a page outside navigation is fine; leaving it unclaimed is not.
    expect($orphans)->toBe([]);
});

test('the application workspace keeps Jobs selected in the sidebar', function (): void {
    [$company, , $application] = renderFixture();

    $patterns = (array) JobResource::getNavigationItemActiveRoutePattern();

    expect(collect($patterns)->contains(
        fn (string $pattern): bool => fnmatch($pattern, 'filament.admin.resources.applications.view'),
    ))->toBeTrue();

    // And the rendered sidebar really marks the Jobs item — and only the Jobs
    // item — as active, rather than leaving nothing selected.
    $html = $this->get(ApplicationResource::getUrl('view', [
        'record' => $application,
        'section' => 'summary',
    ], tenant: $company))->assertOk()->getContent();

    // Each sidebar entry is an <li> carrying `fi-sidebar-item`, plus `fi-active`
    // when it is the current one; the link it wraps says which page that is.
    preg_match_all(
        '/<li\b(?<attributes>[^>]*fi-sidebar-item[^>]*)>(?<body>.*?)<\/li>/s',
        (string) $html,
        $matches,
        PREG_SET_ORDER,
    );

    $active = collect($matches)
        ->filter(fn (array $item): bool => str_contains($item['attributes'], 'fi-active'))
        ->map(function (array $item): string {
            preg_match('/href="(?<url>[^"]*)"/', $item['body'], $link);

            return $link['url'] ?? '';
        })
        ->values();

    expect($active)->toHaveCount(1)
        ->and($active->first())->toContain(JobResource::getUrl('index', tenant: $company));
});
