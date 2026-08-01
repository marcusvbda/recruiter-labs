<?php

use App\Models\Application;
use App\Models\Status;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('prevents deleting a status that still has an application pointing to it', function () {
    $application = Application::factory()->create();
    $status = $application->status;

    expect(fn () => $status->delete())->toThrow(QueryException::class);

    expect(Application::query()->find($application->id))->not->toBeNull()
        ->and(Status::query()->find($status->id))->not->toBeNull();
});

it('allows deleting a status once no application references it', function () {
    $status = Status::factory()->create();

    $status->delete();

    expect(Status::query()->find($status->id))->toBeNull();
});
