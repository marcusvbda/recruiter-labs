<?php

use App\Filament\Resources\Jobs\Pages\ListJobs;
use App\Filament\Resources\Referrals\Pages\ListReferrals;
use App\Models\Company;
use App\Models\Job;
use App\Models\Referral;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('shows the job key and copies its public URL when clicked', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $url = route('job.show', ['key' => $job->key]);

    actAsCompany($company);

    Livewire::test(ListJobs::class)
        ->assertTableColumnStateSet('key', $job->key, $job)
        ->assertTableColumnExists(
            'key',
            fn (TextColumn $column): bool => $column->isCopyable($job->key)
                && ($column->getCopyableState($job->key) === $url)
                && ($column->getUrl() === null),
            $job,
        );
});

it('shows the referral key and copies its public URL when clicked', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $user = User::factory()->create();
    $referral = Referral::factory()->for($company)->for($job)->for($user)->create();
    $url = route('referral.show', ['key' => $referral->key]);

    actAsCompany($company);

    Livewire::test(ListReferrals::class)
        ->assertTableColumnStateSet('key', $referral->key, $referral)
        ->assertTableColumnExists(
            'key',
            fn (TextColumn $column): bool => $column->isCopyable($referral->key)
                && ($column->getCopyableState($referral->key) === $url)
                && ($column->getUrl() === null),
            $referral,
        )
        ->assertTableColumnExists(
            'published',
            fn (IconColumn $column): bool => $column->isBoolean(),
            $referral,
        )
        ->assertTableColumnDoesNotExist('public_url');
});
