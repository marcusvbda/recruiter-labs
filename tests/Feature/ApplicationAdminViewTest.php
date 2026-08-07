<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationSource;
use App\Enums\JobCriteriaProcessingStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\Company;
use App\Models\CompanyScoringSetting;
use App\Models\Job;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('does not resolve an application through another tenant resource URL', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignApplication = Application::factory()->for($otherCompany)->create();

    actAsCompany($company);

    $this->get(ApplicationResource::getUrl('view', [
        'record' => $foreignApplication,
    ], tenant: $company))->assertNotFound();
});

it('denies an internally inconsistent application even to a company member', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignJob = Job::factory()->for($otherCompany)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $foreignJob->id,
    ]);
    $user = User::factory()->create();
    $user->companies()->attach($company);

    expect(Gate::forUser($user)->allows('view', $application))->toBeFalse();
});

it('moves an application only to a status from its own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $currentStatus = Status::factory()->for($company)->create(['name' => 'Applied', 'order' => 1]);
    $nextStatus = Status::factory()->for($company)->create(['name' => 'Interview', 'order' => 2]);
    $foreignStatus = Status::factory()->for($otherCompany)->create(['name' => 'Foreign status']);
    $application = Application::factory()->for($company)->create(['status_id' => $currentStatus->id]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('moveStatus', ['status_id' => $nextStatus->id])
        ->assertNotified(__('applications.admin.actions.status_updated'));

    expect($application->fresh()->status_id)->toBe($nextStatus->id);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('moveStatus', ['status_id' => $foreignStatus->id]);

    expect($application->fresh()->status_id)->toBe($nextStatus->id);
});

it('shows each pipeline status color beside its name in the move status action', function () {
    $company = Company::factory()->create();
    $currentStatus = Status::factory()->for($company)->create([
        'name' => 'Applied',
        'color' => '#3b82f6',
        'order' => 1,
    ]);
    Status::factory()->for($company)->create([
        'name' => 'Interview',
        'color' => '#8b5cf6',
        'order' => 2,
    ]);
    $application = Application::factory()->for($company)->create([
        'status_id' => $currentStatus->id,
    ]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertActionExists('moveStatus', function (Action $action) use ($currentStatus): bool {
            $livewire = $action->getLivewire();

            if (! $livewire instanceof ViewApplication) {
                return false;
            }

            $statusField = $action->getSchema(Schema::make($livewire))
                ?->getComponent('status_id', withHidden: true);

            if (! $statusField instanceof Select || ! $statusField->isHtmlAllowed()) {
                return false;
            }

            $options = $statusField->getOptions();

            return str_contains($options[$currentStatus->id], 'fill="#3b82f6"')
                && str_contains(implode('', $options), 'fill="#8b5cf6"');
        });
});

it('links kanban cards to the tenant-scoped application page and shows compact AI state', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_status' => ApplicationAnalysisStatus::Pending,
    ]);

    actAsCompany($company);

    $url = ApplicationResource::getUrl('view', ['record' => $application], tenant: $company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee($url, escape: false)
        ->assertSee(__('applications.admin.ai.states.pending.label'));
});

it('links back to the pipeline tab from the application view', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create();

    actAsCompany($company);

    $expectedUrl = JobResource::getUrl('view', [
        'record' => $application->job,
        'section' => 'pipeline',
    ], tenant: $company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertActionExists('backToPipeline', fn(Action $action): bool => $action->getUrl() === $expectedUrl);
});

it('provides every admin application status translation', function (string $locale) {
    foreach (ApplicationAnalysisStatus::cases() as $status) {
        expect(Lang::hasForLocale("applications.admin.ai.states.{$status->value}.label", $locale))->toBeTrue()
            ->and(Lang::hasForLocale("applications.admin.ai.states.{$status->value}.title", $locale))->toBeTrue()
            ->and(Lang::hasForLocale("applications.admin.ai.states.{$status->value}.description", $locale))->toBeTrue();
    }

    expect(Lang::hasForLocale('applications.admin.tabs.overview', $locale))->toBeTrue()
        ->and(Lang::hasForLocale('applications.admin.tabs.application', $locale))->toBeTrue()
        ->and(Lang::hasForLocale('applications.admin.tabs.documents', $locale))->toBeTrue()
        ->and(Lang::hasForLocale('applications.admin.tabs.ai_analysis', $locale))->toBeTrue();
})->with(['en', 'pt_BR', 'es']);

it('shows the awaiting-criteria state without polling when the job has no AI criteria', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertSee(__('applications.admin.ai.states.awaiting_criteria.title'))
        ->assertDontSee('wire:poll', escape: false);
});

it('shows a poller only while the analysis is pending or processing', function (ApplicationAnalysisStatus $status) {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create(['analysis_status' => $status]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertSee('wire:poll.5s', escape: false);
})->with([
    'pending' => [ApplicationAnalysisStatus::Pending],
    'processing' => [ApplicationAnalysisStatus::Processing],
]);

it('renders a calmer static state for pending and an active spinner for processing', function () {
    $company = Company::factory()->create();
    $pendingApplication = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::Pending,
    ]);
    $processingApplication = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::Processing,
    ]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $pendingApplication->getRouteKey()])
        ->assertDontSee('rl-analysis-active-indicator', escape: false);

    Livewire::test(ViewApplication::class, ['record' => $processingApplication->getRouteKey()])
        ->assertSee('rl-analysis-active-indicator', escape: false);
});

it('shows the aggregate score and per-criterion breakdown once completed', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_score' => 82.5,
    ]);
    ApplicationCriterionScore::query()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'score' => 85,
        'reason' => 'Strong backend track record.',
        'confidence' => 'high',
    ]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertSee('82')
        ->assertSee('Laravel expertise')
        ->assertSee('85')
        ->assertSee('Strong backend track record.')
        ->assertSee('High');
});

it('always shows the reprocess action, labelled per analysis status', function (ApplicationAnalysisStatus $status, string $expectedLabelKey) {
    $company = Company::factory()->create();
    // Every status other than AwaitingCriteria can only exist once the job's criteria have
    // completed processing (that's the only path AnalyzeApplicationFit takes to reach them),
    // so the fixture mirrors that precondition.
    $job = Job::factory()->for($company)->create();
    $job->jobCriteria()->create([
        'company_id' => $company->id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'analysis_status' => $status,
    ]);
    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertActionVisible('reprocessApplicationAnalysis')
        ->assertSee(__("applications.admin.ai.{$expectedLabelKey}"));
})->with([
    'awaiting criteria' => [ApplicationAnalysisStatus::AwaitingCriteria, 'start_action'],
    'pending' => [ApplicationAnalysisStatus::Pending, 'reprocess_action'],
    'processing' => [ApplicationAnalysisStatus::Processing, 'reprocess_action'],
    'completed' => [ApplicationAnalysisStatus::Completed, 'reprocess_action'],
    'failed' => [ApplicationAnalysisStatus::Failed, 'reprocess_action'],
    'pending quota' => [ApplicationAnalysisStatus::PendingQuota, 'reprocess_action'],
]);

it('dispatches a new analysis when the reprocess action is called, regardless of the starting status', function (ApplicationAnalysisStatus $status) {
    $company = Company::factory()->create();
    // These statuses can only exist once the job's criteria have completed processing, so the
    // fixture mirrors that precondition to prove the manual trigger dispatches the job.
    $job = Job::factory()->for($company)->create();
    $job->jobCriteria()->create([
        'company_id' => $company->id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'analysis_status' => $status,
    ]);
    actAsCompany($company);
    Queue::fake();

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('reprocessApplicationAnalysis');

    Queue::assertPushed(AnalyzeApplicationFit::class);
})->with([
    'pending' => [ApplicationAnalysisStatus::Pending],
    'processing' => [ApplicationAnalysisStatus::Processing],
    'completed' => [ApplicationAnalysisStatus::Completed],
    'failed' => [ApplicationAnalysisStatus::Failed],
    'pending quota' => [ApplicationAnalysisStatus::PendingQuota],
]);

it('forces a full page reload back to the AI analysis tab after the reprocess action runs', function (ApplicationAnalysisStatus $status) {
    // Regression test: the action used to only reassign $this->record and rely on Livewire's
    // in-place re-render, which left the page showing the stale "Completed" tab content because
    // Filament's SPA-mode wire:navigate short-circuits a redirect to the "same" URL. The fix
    // issues an explicit redirect with navigate: false to force a real browser navigation, which
    // re-mounts the page and picks up the freshly persisted analysis_status.
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->jobCriteria()->create([
        'company_id' => $company->id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'analysis_status' => $status,
    ]);
    actAsCompany($company);
    Queue::fake();

    $expectedUrl = ApplicationResource::getUrl('view', [
        'record' => $application,
        'section' => 'ai-analysis::tab',
    ], tenant: $company);

    $test = Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('reprocessApplicationAnalysis')
        ->assertRedirect($expectedUrl);

    Queue::assertPushed(AnalyzeApplicationFit::class);

    expect($test->effects)
        ->not->toHaveKey('redirectUsingNavigate');
})->with([
    'pending' => [ApplicationAnalysisStatus::Pending],
    'processing' => [ApplicationAnalysisStatus::Processing],
    'completed' => [ApplicationAnalysisStatus::Completed],
    'failed' => [ApplicationAnalysisStatus::Failed],
    'pending quota' => [ApplicationAnalysisStatus::PendingQuota],
]);

it('does not dispatch an analysis when the reprocess action is called while awaiting criteria that are not ready', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ]);
    actAsCompany($company);
    Queue::fake();

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('reprocessApplicationAnalysis');

    Queue::assertNotPushed(AnalyzeApplicationFit::class);
});

it('hides the AI badge on the pipeline card while awaiting criteria', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertDontSee(__('applications.admin.ai.states.awaiting_criteria.label'));
});

it('shows the overall application score computed with default weights and the referral bonus applied', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_score' => 80.0,
        'source' => ApplicationSource::Referral,
    ]);

    actAsCompany($company);

    // No CompanyScoringSetting row exists, so the model falls back to the 60/40 defaults:
    // (80 * 60 + 100 * 40) / 100 = 88.
    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertSee(__('applications.admin.overview.overall_score.heading'))
        ->assertSee('88/100')
        ->assertSee(__('applications.admin.overview.overall_score.ai_component', ['score' => 80, 'weight' => 60]))
        ->assertSee(__('applications.admin.overview.overall_score.referral_yes', ['weight' => 40]));
});

it('shows a lower overall application score with no referral bonus for a direct application', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_score' => 80.0,
        'source' => ApplicationSource::Direct,
    ]);

    actAsCompany($company);

    // No CompanyScoringSetting row exists, so the model falls back to the 60/40 defaults:
    // (80 * 60 + 0 * 40) / 100 = 48.
    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertSee('48/100')
        ->assertSee(__('applications.admin.overview.overall_score.ai_component', ['score' => 80, 'weight' => 60]))
        ->assertSee(__('applications.admin.overview.overall_score.referral_no', ['weight' => 40]));
});

it('computes the overall application score using the company custom scoring weights', function () {
    $company = Company::factory()->create();
    CompanyScoringSetting::factory()->for($company)->create([
        'analysis_weight' => 70,
        'referral_weight' => 30,
    ]);
    $application = Application::factory()->for($company)->create([
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_score' => 80.0,
        'source' => ApplicationSource::Referral,
    ]);

    actAsCompany($company);

    // With the company's custom 70/30 weights: (80 * 70 + 100 * 30) / 100 = 86,
    // which must differ from the 88 the 60/40 default would produce for the same inputs.
    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertSee('86/100')
        ->assertDontSee('88/100')
        ->assertSee(__('applications.admin.overview.overall_score.ai_component', ['score' => 80, 'weight' => 70]))
        ->assertSee(__('applications.admin.overview.overall_score.referral_yes', ['weight' => 30]));
});

it('shows the overall score as not yet available while the AI analysis has not completed', function (ApplicationAnalysisStatus $status) {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create([
        'analysis_status' => $status,
        'analysis_score' => null,
    ]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->assertOk()
        ->assertSee(__('applications.admin.overview.overall_score.heading'))
        ->assertSee(__('applications.admin.overview.overall_score.not_available'));
})->with([
    'pending' => [ApplicationAnalysisStatus::Pending],
    'awaiting criteria' => [ApplicationAnalysisStatus::AwaitingCriteria],
    'processing' => [ApplicationAnalysisStatus::Processing],
]);

it('orders kanban cards within a column by AI score descending, with unscored applications sorted last', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();

    $lowScore = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => 40.0,
        'created_at' => now()->subDays(3),
    ]);
    $highScore = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => 90.0,
        'created_at' => now()->subDays(1),
    ]);
    $unscored = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => null,
        'created_at' => now()->subDays(2),
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSeeHtmlInOrder([
            "data-record-id=\"{$highScore->id}\"",
            "data-record-id=\"{$lowScore->id}\"",
            "data-record-id=\"{$unscored->id}\"",
        ]);
});

it('orders unscored kanban cards by created_at ascending, oldest application first', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();

    $newer = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => null,
        'created_at' => now()->subDay(),
    ]);
    $older = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => null,
        'created_at' => now()->subWeek(),
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSeeHtmlInOrder([
            "data-record-id=\"{$older->id}\"",
            "data-record-id=\"{$newer->id}\"",
        ]);
});

it('orders scored kanban cards with the higher score first', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();

    $lower = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => 55.0,
        'created_at' => now()->subDay(),
    ]);
    $higher = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => 95.0,
        'created_at' => now()->subWeek(),
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSeeHtmlInOrder([
            "data-record-id=\"{$higher->id}\"",
            "data-record-id=\"{$lower->id}\"",
        ]);
});

it('keeps the kanban column totals query independent from card ordering, so it stays compatible with strict GROUP BY databases like PostgreSQL', function () {
    // PostgreSQL rejects an ORDER BY column that is neither in the GROUP BY clause
    // nor wrapped in an aggregate. The card ordering (`analysis_score`, `created_at`,
    // `updated_at`) must therefore never leak into the `GROUP BY status_id` totals
    // query. SQLite doesn't enforce this rule, so the only way to guard against a
    // regression here is to inspect the generated SQL directly.
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_score' => 42.0,
    ]);

    actAsCompany($company);

    $capturedQueries = [];
    DB::listen(function ($event) use (&$capturedQueries): void {
        $capturedQueries[] = $event->sql;
    });

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->instance()
        ->getColumnTotals();

    $totalsQueries = array_values(array_filter(
        $capturedQueries,
        fn(string $sql): bool => str_contains($sql, 'group by'),
    ));

    expect($totalsQueries)->not->toBeEmpty();

    foreach ($totalsQueries as $sql) {
        expect($sql)
            ->not->toContain('order by')
            ->not->toContain('analysis_score')
            ->not->toContain('created_at')
            ->not->toContain('updated_at');
    }
});

it('shows the AI badge on the pipeline card for every other analysis status', function (ApplicationAnalysisStatus $status) {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $pipelineStatus = Status::factory()->for($company)->create();
    Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $pipelineStatus->id,
        'analysis_status' => $status,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee(__("applications.admin.ai.states.{$status->value}.label"));
})->with([
    'pending' => [ApplicationAnalysisStatus::Pending],
    'processing' => [ApplicationAnalysisStatus::Processing],
    'completed' => [ApplicationAnalysisStatus::Completed],
    'failed' => [ApplicationAnalysisStatus::Failed],
    'pending quota' => [ApplicationAnalysisStatus::PendingQuota],
]);
