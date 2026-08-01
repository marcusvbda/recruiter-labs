<?php

use App\Filament\Resources\Jobs\Pages\ListJobs;
use App\Models\Company;
use App\Models\Job;
use Database\Seeders\PlanSeeder;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('defaults jobs to unpublished', function () {
    $job = Job::factory()->create();

    expect($job->published)->toBeFalse()
        ->and($job->fresh()->published)->toBeFalse();
});

it('shows published as a boolean and removes created at from the jobs list', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create(['published' => true]);

    actAsCompany($company);

    Livewire::test(ListJobs::class)
        ->assertTableColumnStateSet('published', true, $job)
        ->assertTableColumnExists(
            'published',
            fn (IconColumn $column): bool => $column->isBoolean(),
            $job,
        )
        ->assertTableColumnDoesNotExist('created_at');
});
