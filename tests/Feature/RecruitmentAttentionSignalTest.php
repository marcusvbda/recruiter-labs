<?php

use App\Enums\RecruitmentAttentionType;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use App\Models\User;
use App\Services\RecruitmentAttentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @return array{0: Company, 1: User, 2: Job} */
function attentionFixture(array $jobAttributes = []): array
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $recruiter = User::factory()->create();
    $recruiter->companies()->attach($company);

    $job = Job::factory()->create([
        'company_id' => $company->getKey(),
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
        ...$jobAttributes,
    ]);

    return [$company, $recruiter, $job];
}

/** @return list<string> */
function attentionTypesFor(Company $company, User $recruiter): array
{
    return app(RecruitmentAttentionService::class)
        ->for($company, $recruiter)
        ->items
        ->map(fn ($item): string => $item->type->value)
        ->all();
}

test('a new job with applications and no interviews yet is not called stalled', function (): void {
    [$company, $recruiter, $job] = attentionFixture();

    Application::factory()->count(3)->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_entered_at' => CarbonImmutable::now(),
    ]);

    expect(attentionTypesFor($company, $recruiter))
        ->not->toContain(RecruitmentAttentionType::JobStalled->value);
});

test('a job is called stalled only once candidates are genuinely overdue', function (): void {
    [$company, $recruiter, $job] = attentionFixture();

    $status = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', false)
        ->orderBy('order')
        ->firstOrFail();
    $status->forceFill(['attention_after_days' => 4])->save();

    Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $status->getKey(),
        'status_entered_at' => CarbonImmutable::now()->subDays(9),
    ]);

    expect(attentionTypesFor($company, $recruiter))
        ->toContain(RecruitmentAttentionType::JobStalled->value)
        ->toContain(RecruitmentAttentionType::StageOverdue->value);
});

test('a finalist awaiting a decision raises one item, not two for the same decision', function (): void {
    [$company, $recruiter, $job] = attentionFixture();

    $finalStage = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_final_stage', true)
        ->where('is_hired', false)
        ->firstOrFail();
    $finalStage->forceFill(['attention_after_days' => 3])->save();

    Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $finalStage->getKey(),
        'status_entered_at' => CarbonImmutable::now()->subDays(10),
    ]);

    $types = attentionTypesFor($company, $recruiter);

    expect($types)->toContain(RecruitmentAttentionType::DecisionPending->value)
        ->and($types)->not->toContain(RecruitmentAttentionType::StageOverdue->value);
});

test('a campaign ending without finalists explains itself without a generic stalled item', function (): void {
    [$company, $recruiter, $job] = attentionFixture([
        'ends_at' => CarbonImmutable::now()->addDays(3)->toDateString(),
    ]);

    $status = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', false)
        ->orderBy('order')
        ->firstOrFail();
    $status->forceFill(['attention_after_days' => 4])->save();

    Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $status->getKey(),
        'status_entered_at' => CarbonImmutable::now()->subDays(9),
    ]);

    $types = attentionTypesFor($company, $recruiter);

    expect($types)->toContain(RecruitmentAttentionType::JobEndingWithoutFinalists->value)
        ->and($types)->not->toContain(RecruitmentAttentionType::JobStalled->value);
});
