<?php

use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\EmailNotificationType;
use App\Events\ApplicationEnteredStatus;
use App\Listeners\SendStatusEnterEmail;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\Pipeline;
use App\Models\Plan;
use App\Models\Status;
use App\Services\RecruitmentEmailDispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

/**
 * @param  array<string, mixed>  $statusAttributes
 * @return array{Application, Status}
 */
function stageEmailFixture(array $statusAttributes): array
{
    $company = Company::factory()->create();
    $pipeline = Pipeline::factory()->create(['company_id' => $company->getKey()]);
    $status = Status::factory()->create([
        'company_id' => $company->getKey(),
        'pipeline_id' => $pipeline->getKey(),
        'name' => 'Interview',
        ...$statusAttributes,
    ]);

    $job = Job::factory()->create(['company_id' => $company->getKey(), 'name' => 'Senior Engineer', 'pipeline_id' => $pipeline->getKey()]);
    $candidate = Candidate::factory()->create(['company_id' => $company->getKey(), 'name' => 'Alex Candidate', 'email' => 'alex@example.com']);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'candidate_id' => $candidate->getKey(),
        'status_id' => $status->getKey(),
    ]);

    return [$application, $status];
}

test('a stage sends its configured template when every variable resolves', function (): void {
    [$application, $status] = stageEmailFixture([]);

    $template = EmailTemplate::factory()->create([
        'company_id' => $application->company_id,
        'subject' => 'Next step for {{ job.title }}',
        'body' => '<p>Hi {{ candidate.name }},</p>',
    ]);

    $status->forceFill(['sends_email' => true, 'email_template_id' => $template->getKey()])->save();

    $dispatcher = Mockery::mock(RecruitmentEmailDispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->withArgs(function ($company, $type, $context) use ($application): bool {
            return $type === EmailNotificationType::PipelineStatus
                && $context->subject === 'Next step for Senior Engineer'
                && $context->body === '<p>Hi Alex Candidate,</p>'
                && $context->applicationId === (int) $application->getKey();
        })
        ->andReturn(true);

    app()->instance(RecruitmentEmailDispatcher::class, $dispatcher);

    app(SendStatusEnterEmail::class)->handle(
        new ApplicationEnteredStatus((int) $application->getKey(), (int) $status->getKey(), null),
    );
});

test('a template variable that cannot be resolved blocks the send and records the failure without touching the stage', function (): void {
    [$application, $status] = stageEmailFixture([]);

    $application->candidate->forceFill(['phone' => null])->save();

    $template = EmailTemplate::factory()->create([
        'company_id' => $application->company_id,
        'subject' => 'Call you on {{ candidate.phone }}',
        'body' => '<p>Hi {{ candidate.name }},</p>',
    ]);

    $status->forceFill(['sends_email' => true, 'email_template_id' => $template->getKey()])->save();

    $dispatcher = Mockery::mock(RecruitmentEmailDispatcher::class);
    $dispatcher->shouldNotReceive('dispatch');
    app()->instance(RecruitmentEmailDispatcher::class, $dispatcher);

    app(SendStatusEnterEmail::class)->handle(
        new ApplicationEnteredStatus((int) $application->getKey(), (int) $status->getKey(), null),
    );

    $failure = CandidateCommunicationMessage::query()
        ->where('company_id', $application->company_id)
        ->first();

    expect($failure)->not->toBeNull()
        ->and($failure->status)->toBe(CandidateCommunicationMessageStatus::Failed)
        ->and($failure->authorized_body)->toContain('candidate.phone')
        // The human-validated transition stays exactly where it was committed.
        ->and((int) $application->fresh()->status_id)->toBe((int) $status->getKey());
});

test('a retired template is treated as a configuration problem instead of being sent', function (): void {
    [$application, $status] = stageEmailFixture([]);

    $template = EmailTemplate::factory()->unavailable()->create([
        'company_id' => $application->company_id,
        'subject' => 'Hello',
        'body' => '<p>Hi {{ candidate.name }},</p>',
    ]);

    $status->forceFill(['sends_email' => true, 'email_template_id' => $template->getKey()])->save();

    $dispatcher = Mockery::mock(RecruitmentEmailDispatcher::class);
    $dispatcher->shouldNotReceive('dispatch');
    app()->instance(RecruitmentEmailDispatcher::class, $dispatcher);

    app(SendStatusEnterEmail::class)->handle(
        new ApplicationEnteredStatus((int) $application->getKey(), (int) $status->getKey(), null),
    );

    expect(CandidateCommunicationMessage::query()->where('company_id', $application->company_id)->count())->toBe(1);
});

test('a template a stage still sends cannot be deleted', function (): void {
    [$application, $status] = stageEmailFixture([]);

    $template = EmailTemplate::factory()->create(['company_id' => $application->company_id]);
    $status->forceFill(['sends_email' => true, 'email_template_id' => $template->getKey()])->save();

    expect(fn () => DB::table('email_templates')->where('id', $template->getKey())->delete())
        ->toThrow(QueryException::class);
});
