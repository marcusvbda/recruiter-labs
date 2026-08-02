<?php

use App\Filament\Pages\Settings;
use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\Plan;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Action;
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
            'published' => true,
            'jobCriteria' => [],
            'acceptedCvTypes' => [CvFileType::query()->firstOrFail()->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified($expectedNotification);

    expect(Job::query()->where('name', 'Blocked by plan limit')->exists())->toBeFalse();
});
