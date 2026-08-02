<?php

use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\ListJobs;
use App\Filament\Resources\Jobs\Pages\ViewJob;
use App\Filament\Resources\Jobs\Widgets\JobApplicationStatusChart;
use App\Filament\Resources\Jobs\Widgets\JobOverviewStats;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobClick;
use App\Models\Status;
use App\Services\JobDashboardService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->travelTo('2026-08-15 12:00:00');
});

afterEach(function () {
    $this->travelBack();
});

it('builds the job dashboard metrics and rankings', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create([
        'starts_at' => '2026-08-10',
        'ends_at' => '2026-08-20',
    ]);
    $applied = Status::factory()->for($company)->create(['name' => 'Applied', 'order' => 1]);
    $hired = Status::factory()->for($company)->create([
        'name' => 'Hired',
        'order' => 2,
        'is_hired' => true,
    ]);

    Application::factory()->count(2)->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $applied->id,
    ]);
    Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $hired->id,
    ]);

    $linkedinClicks = JobClick::factory()->count(3)->for($company)->for($job)->create([
        'ip_address' => '203.0.113.10',
    ]);
    $linkedinClicks->each(fn (JobClick $click) => $click->utmParameters()->create([
        'name' => 'utm_source',
        'value' => 'linkedin',
    ]));

    $googleClick = JobClick::factory()->for($company)->for($job)->create([
        'ip_address' => '203.0.113.20',
    ]);
    $googleClick->utmParameters()->create([
        'name' => 'utm_source',
        'value' => 'google',
    ]);

    $dashboard = app(JobDashboardService::class)->get($job);

    expect($dashboard['clicks_count'])->toBe(4)
        ->and($dashboard['applications_count'])->toBe(3)
        ->and($dashboard['hired_count'])->toBe(1)
        ->and($dashboard['running_days'])->toBe(5)
        ->and($dashboard['remaining_days'])->toBe(5)
        ->and($dashboard['has_ended'])->toBeFalse()
        ->and($dashboard['status_distribution'])->toBe([
            ['name' => 'Applied', 'color' => $applied->color, 'count' => 2],
            ['name' => 'Hired', 'color' => $hired->color, 'count' => 1],
        ])
        ->and($dashboard['utm_ranking'][0])->toBe([
            'name' => 'utm_source',
            'value' => 'linkedin',
            'clicks' => 3,
        ])
        ->and($dashboard['ip_ranking'][0])->toBe([
            'ip_address' => '203.0.113.10',
            'clicks' => 3,
        ]);
});

it('shows every tracked UTM value even when the ranking has more than ten entries', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();

    foreach (range(1, 11) as $position) {
        $click = JobClick::factory()->for($company)->for($job)->create();
        $click->utmParameters()->create([
            'name' => 'utm_source',
            'value' => "campaign-{$position}",
        ]);
    }

    $utmRanking = app(JobDashboardService::class)->get($job)['utm_ranking'];

    expect($utmRanking)->toHaveCount(11)
        ->and(collect($utmRanking)->pluck('value'))->toContain('campaign-11');
});

it('renders dashboard and pipeline tabs on the job view page', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create(['name' => 'Platform Engineer']);

    actAsCompany($company);

    $this->get(JobResource::getUrl('view', ['record' => $job], tenant: $company))
        ->assertSuccessful()
        ->assertSee('Platform Engineer')
        ->assertSee(__('jobs.view_tabs.dashboard'))
        ->assertSee(__('jobs.view_tabs.pipeline'))
        ->assertSeeLivewire(ViewJob::class)
        ->assertSeeLivewire(JobOverviewStats::class)
        ->assertSeeLivewire(JobApplicationStatusChart::class)
        ->assertSeeLivewire(JobPipelineKanban::class);
});

it('selects the pipeline tab from the section query string', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();

    actAsCompany($company);

    $this->get(JobResource::getUrl('view', [
        'record' => $job,
        'section' => 'pipeline',
    ], tenant: $company))
        ->assertSuccessful()
        ->assertSee('activeTab: 2', escape: false)
        ->assertSee('data-tab-key="pipeline"', escape: false);
});

it('replaces the pipeline list action with the job view action', function () {
    $company = Company::factory()->create();
    Job::factory()->for($company)->create();

    actAsCompany($company);

    Livewire::test(ListJobs::class)
        ->assertTableActionExists('view')
        ->assertTableActionDoesNotExist('pipeline');
});
