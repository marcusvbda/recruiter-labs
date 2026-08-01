<?php

use App\Models\AutomationEvent;
use App\Models\Job;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('persists the morph map alias instead of the FQCN when associating a job', function () {
    $job = Job::factory()->create();

    $event = AutomationEvent::factory()
        ->for($job->company)
        ->make(['automatable_id' => null, 'automatable_type' => null]);
    $event->automatable()->associate($job);
    $event->save();

    expect($event->automatable_type)->toBe('job')
        ->and($event->automatable_type)->not->toBe(Job::class);

    $persistedType = AutomationEvent::query()
        ->whereKey($event->id)
        ->value('automatable_type');

    expect($persistedType)->toBe('job');
});

it('resolves the automatable relation back to the correct job instance', function () {
    $job = Job::factory()->create();

    $event = AutomationEvent::factory()
        ->for($job->company)
        ->make(['automatable_id' => null, 'automatable_type' => null]);
    $event->automatable()->associate($job);
    $event->save();

    expect($event->fresh()->automatable->is($job))->toBeTrue();
});
