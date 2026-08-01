<?php

use App\Enums\ApplicationLocale;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobClick;
use App\Models\Referral;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('traces public job page clicks with the visitor IP and arbitrary UTM parameters', function () {
    $job = Job::factory()->create([
        'published' => true,
        'starts_at' => today()->subDay(),
        'ends_at' => today()->addDay(),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get(route('job.show', [
            'key' => $job->key,
            'utm_source' => 'linkedin',
            'utm_creative_variant' => 'blue-card',
            'untracked' => 'ignored',
        ]))
        ->assertSuccessful();

    $click = JobClick::query()->sole();

    expect($click->job_id)->toBe($job->id)
        ->and($click->company_id)->toBe($job->company_id)
        ->and($click->referral_id)->toBeNull()
        ->and($click->ip_address)->toBe('203.0.113.10')
        ->and($click->utmParameters()->where('name', 'utm_source')->value('value'))->toBe('linkedin')
        ->and($click->utmParameters()->where('name', 'utm_creative_variant')->value('value'))->toBe('blue-card')
        ->and($click->utmParameters()->count())->toBe(2);
});

it('attributes referral page clicks to the referral and its job', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create([
        'application_locale' => ApplicationLocale::Spanish,
        'published' => true,
    ]);
    $user = User::factory()->create();
    $referral = Referral::factory()->for($company)->for($job)->for($user)->create();

    $this->get(route('referral.show', [
        'key' => $referral->key,
        'utm_source' => 'employee',
    ]))->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('translations.locale', 'es-ES')
            ->where('translations.alerts.referral', 'Te han recomendado para esta oportunidad. Nos alegra que estés aquí.'));

    $click = JobClick::query()->sole();

    expect($click->job_id)->toBe($job->id)
        ->and($click->referral_id)->toBe($referral->id)
        ->and($click->utmParameters()->value('value'))->toBe('employee');
});

it('does not trace unavailable jobs or authenticated previews', function () {
    $company = Company::factory()->create();
    $unavailableJob = Job::factory()->for($company)->create(['published' => false]);
    $previewedJob = Job::factory()->for($company)->create(['published' => true]);

    $this->get(route('job.show', ['key' => $unavailableJob->key]))
        ->assertNotFound();

    actAsCompany($company);

    $this->get(route('job.preview', ['key' => $previewedJob->key]))
        ->assertSuccessful();

    expect(JobClick::query()->count())->toBe(0);
});
