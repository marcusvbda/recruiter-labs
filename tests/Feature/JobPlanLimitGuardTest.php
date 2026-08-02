<?php

use App\Enums\Limit;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Jobs\Pages\ViewJob;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->seed(CvFileTypeSeeder::class);
    $this->travelTo('2026-08-15 12:00:00');
});

afterEach(function () {
    $this->travelBack();
});

it('halts job creation at the active job limit and offers a link to manage the plan', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    Job::factory()->count(3)->for($company)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    actAsCompany($company);

    $expectedNotification = Notification::make()
        ->title(__('settings.plan.limit_reached'))
        ->body(__('settings.errors.job_limit_reached'))
        ->warning()
        ->actions([
            Action::make('managePlan')
                ->label(__('settings.topbar.manage_plan'))
                ->url(Settings::getUrl(['section' => 'plan'], tenant: $company))
                ->button(),
        ]);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Blocked by plan limit',
            'published' => false,
            'jobCriteria' => [],
            'acceptedCvTypes' => [CvFileType::query()->firstOrFail()->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified($expectedNotification);

    expect(Job::query()->where('name', 'Blocked by plan limit')->exists())->toBeFalse();
});

it('halts a pipeline application when the monthly application limit is reached', function () {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update([
        'limits' => [...$plan->limits, Limit::Applications->value => 1],
    ]);

    $company = Company::factory()->create(['plan_id' => $plan->id]);
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create(['order' => 0]);
    $existingCandidate = Candidate::factory()->for($company)->create();
    $blockedCandidate = Candidate::factory()->for($company)->create();

    Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'candidate_id' => $existingCandidate->id,
        'status_id' => $status->id,
    ]);

    actAsCompany($company);

    $expectedNotification = Notification::make()
        ->title(__('settings.plan.limit_reached'))
        ->body(__('settings.errors.plan_limit_reached', [
            'limit' => Limit::Applications->label(),
        ]))
        ->warning()
        ->actions([
            Action::make('managePlan')
                ->label(__('settings.topbar.manage_plan'))
                ->url(Settings::getUrl(['section' => 'plan'], tenant: $company))
                ->button(),
        ]);

    Livewire::test(ViewJob::class, ['record' => $job->getRouteKey()])
        ->callAction(
            TestAction::make('addCandidate')->schemaComponent('pipeline-actions', 'content'),
            ['candidate_id' => $blockedCandidate->id],
        )
        ->assertNotified($expectedNotification);

    expect(Application::query()->where('candidate_id', $blockedCandidate->id)->exists())->toBeFalse();
});
