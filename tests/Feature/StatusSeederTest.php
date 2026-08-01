<?php

use App\Models\Company;
use App\Models\Status;
use Database\Seeders\PlanSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('creates the default recruiting pipeline for every company', function () {
    $companies = Company::factory()->count(2)->create();

    $this->seed(StatusSeeder::class);

    foreach ($companies as $company) {
        expect($company->statuses()->orderBy('order')->pluck('name')->all())->toBe([
            'Applied',
            'Screening',
            'Interview',
            'Offer',
            'Hired',
            'Rejected',
        ])->and($company->statuses()->where('is_hired', true)->pluck('name')->all())->toBe(['Hired']);
    }
});

it('is idempotent and preserves customized existing statuses', function () {
    $company = Company::factory()->create();

    Status::factory()->for($company)->create([
        'name' => 'Applied',
        'color' => '#000000',
        'order' => 99,
    ]);

    $this->seed(StatusSeeder::class);
    $this->seed(StatusSeeder::class);

    $applied = $company->statuses()->where('name', 'Applied')->sole();

    expect($company->statuses()->count())->toBe(6)
        ->and($applied->color)->toBe('#000000')
        ->and($applied->order)->toBe(99);
});
