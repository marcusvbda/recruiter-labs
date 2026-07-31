<?php

use App\Models\Company;
use App\Models\Job;
use App\Models\Referral;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('auto-generates a unique key when a referral is created', function () {
    $referral = Referral::factory()->create();

    expect($referral->key)->not->toBeNull();
    expect(Str::isUuid($referral->key))->toBeTrue();
});

it('does not allow the key to be set via mass assignment', function () {
    $referral = Referral::create([
        'company_id' => Company::factory()->create()->id,
        'job_id' => Job::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
        'key' => 'not-a-real-key',
    ]);

    expect($referral->key)->not->toBe('not-a-real-key');
    expect(Str::isUuid($referral->key))->toBeTrue();
});

it('generates a different key for every referral', function () {
    $referralA = Referral::factory()->create();
    $referralB = Referral::factory()->create();

    expect($referralA->key)->not->toBe($referralB->key);
});

it('enforces uniqueness of the key at the database level', function () {
    $referral = Referral::factory()->create();

    expect(fn () => Referral::query()->insert([
        'company_id' => $referral->company_id,
        'job_id' => $referral->job_id,
        'user_id' => $referral->user_id,
        'key' => $referral->key,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('does not change the key when the referral is updated', function () {
    $referral = Referral::factory()->create();
    $originalKey = $referral->key;

    $referral->update(['job_id' => Job::factory()->create(['company_id' => $referral->company_id])->id]);

    expect($referral->fresh()->key)->toBe($originalKey);
});
