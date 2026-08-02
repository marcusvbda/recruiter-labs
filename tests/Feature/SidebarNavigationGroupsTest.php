<?php

use App\Filament\Clusters\Automation\AutomationCluster;
use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AutomationEvents\AutomationEventResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\EmailTemplates\EmailTemplatesResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Referrals\ReferralResource;
use App\Filament\Resources\Statuses\StatusesResource;
use App\Models\Company;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('assigns resources to workflow clusters', function () {
    expect(CandidateResource::getCluster())->toBe(RecruitmentCluster::class)
        ->and(StatusesResource::getCluster())->toBe(RecruitmentCluster::class)
        ->and(JobResource::getCluster())->toBe(RecruitmentCluster::class)
        ->and(ReferralResource::getCluster())->toBe(RecruitmentCluster::class)
        ->and(EmailTemplatesResource::getCluster())->toBe(AutomationCluster::class)
        ->and(AutomationEventResource::getCluster())->toBe(AutomationCluster::class);
});

it('orders clusters and their resources', function () {
    expect(RecruitmentCluster::getNavigationSort())->toBe(1)
        ->and(AutomationCluster::getNavigationSort())->toBe(2)
        ->and(CandidateResource::getNavigationSort())->toBe(1)
        ->and(StatusesResource::getNavigationSort())->toBe(2)
        ->and(JobResource::getNavigationSort())->toBe(3)
        ->and(ReferralResource::getNavigationSort())->toBe(4)
        ->and(EmailTemplatesResource::getNavigationSort())->toBe(1)
        ->and(AutomationEventResource::getNavigationSort())->toBe(2);
});

it('prefixes resource URLs with their cluster slug', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    expect(CandidateResource::getUrl('index', tenant: $company))->toEndWith("/admin/{$company->slug}/recruitment/candidates")
        ->and(StatusesResource::getUrl('index', tenant: $company))->toEndWith("/admin/{$company->slug}/recruitment/statuses")
        ->and(JobResource::getUrl('index', tenant: $company))->toEndWith("/admin/{$company->slug}/recruitment/jobs")
        ->and(ReferralResource::getUrl('index', tenant: $company))->toEndWith("/admin/{$company->slug}/recruitment/referrals")
        ->and(EmailTemplatesResource::getUrl('index', tenant: $company))->toEndWith("/admin/{$company->slug}/automation/email-templates")
        ->and(AutomationEventResource::getUrl('index', tenant: $company))->toEndWith("/admin/{$company->slug}/automation/event-hooks");
});

it('renders clusters instead of individual resources in the main sidebar', function () {
    actAsCompany(Company::factory()->create());

    $labels = collect(Filament::getCurrentPanel()->getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item): string => $item->getLabel());

    expect($labels)
        ->toContain('Recruitment', 'Automation', __('settings.navigation_label'));

    expect($labels)->not->toContain('Candidates', 'Statuses', 'Jobs', 'Referrals', 'Email Templates', 'Event Hooks');
});

it('renders useful workspace shortcuts at the bottom of the sidebar', function () {
    $company = Company::factory()->create(['name' => 'Gravity Labs']);
    actAsCompany($company);

    $this->get(Dashboard::getUrl(tenant: $company))
        ->assertSuccessful()
        ->assertSee('data-testid="sidebar-workspace-card"', escape: false)
        ->assertSee('Gravity Labs')
        ->assertSee(JobResource::getUrl('create', tenant: $company), escape: false)
        ->assertSee(Settings::getUrl(tenant: $company), escape: false)
        ->assertSee(__('settings.sidebar.new_job'));
});

it('translates cluster labels and breadcrumbs', function (string $locale, array $labels) {
    app()->setLocale($locale);

    expect([
        RecruitmentCluster::getNavigationLabel(),
        AutomationCluster::getNavigationLabel(),
    ])->toBe($labels)
        ->and([
            RecruitmentCluster::getClusterBreadcrumb(),
            AutomationCluster::getClusterBreadcrumb(),
        ])->toBe($labels);
})->with([
    'English' => ['en', ['Recruitment', 'Automation']],
    'Spanish' => ['es', ['Reclutamiento', 'Automatización']],
    'Brazilian Portuguese' => ['pt_BR', ['Recrutamento', 'Automação']],
]);
