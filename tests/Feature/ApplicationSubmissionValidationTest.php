<?php

use App\Enums\ApplicationLocale;
use App\Enums\ApplicationQuestionType;
use App\Enums\CoverLetterType;
use App\Models\Application;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use App\Models\Plan;
use App\Models\Status;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PlanSeeder::class, CvFileTypeSeeder::class]);
    Storage::fake('local');
});

/** @return array{job: Job, requiredQuestion: JobApplicationQuestion, optionalQuestion: JobApplicationQuestion} */
function submissionValidationFixture(array $jobAttributes = []): array
{
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'business')->sole()->id,
    ]);
    $job = Job::factory()->for($company)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
        ...$jobAttributes,
    ]);
    $job->acceptedCvTypes()->attach(CvFileType::query()->where('extension', 'pdf')->sole());
    Status::factory()->for($company)->create(['order' => 1]);
    $requiredQuestion = JobApplicationQuestion::factory()->for($company)->for($job)->create([
        'question' => 'Portfolio summary',
        'response_type' => ApplicationQuestionType::Textarea,
        'required' => true,
    ]);
    $optionalQuestion = JobApplicationQuestion::factory()->for($company)->for($job)->create([
        'question' => 'Preferred salary',
        'response_type' => ApplicationQuestionType::Number,
        'required' => false,
    ]);

    return compact('job', 'requiredQuestion', 'optionalQuestion');
}

/** @return array<string, mixed> */
function submissionValidationPayload(JobApplicationQuestion $question, array $overrides = []): array
{
    return [
        'name' => 'Validation Candidate',
        'email' => 'validation@example.test',
        'phone_country' => 'IE',
        'phone' => '85 123 4567',
        'cv' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        'answers' => [$question->id => 'A detailed summary of relevant work.'],
        ...$overrides,
    ];
}

it('validates required and optional custom questions', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'answers' => [],
    ]))->assertSessionHasErrors("answers.{$requiredQuestion->id}");

    expect(Application::query()->exists())->toBeFalse();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion))
        ->assertStatus(303);

    expect(Application::query()->sole()->answers()->count())->toBe(1);
});

it('validates numeric answers and rejects manipulated question ids', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion, 'optionalQuestion' => $optionalQuestion] = submissionValidationFixture();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'answers' => [
            $requiredQuestion->id => 'Relevant experience',
            $optionalQuestion->id => 'not-a-number',
            999999 => 'manipulated',
        ],
    ]))->assertSessionHasErrors([
        "answers.{$optionalQuestion->id}",
        'answers.999999',
    ]);

    expect(Application::query()->exists())->toBeFalse();
});

it('rejects numeric answers that cannot fit the persisted decimal value', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion, 'optionalQuestion' => $optionalQuestion] = submissionValidationFixture();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'answers' => [
            $requiredQuestion->id => 'Relevant experience',
            $optionalQuestion->id => '99999999999999999.12345',
        ],
    ]))->assertSessionHasErrors("answers.{$optionalQuestion->id}");
});

it('rejects a question belonging to another job', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture();
    $otherJob = Job::factory()->for($job->company)->create();
    $otherQuestion = JobApplicationQuestion::factory()->for($job->company)->for($otherJob)->create();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'answers' => [
            $requiredQuestion->id => 'Relevant experience',
            $otherQuestion->id => 'Cross-job answer',
        ],
    ]))->assertSessionHasErrors("answers.{$otherQuestion->id}");
});

it('persists text, textarea, and number answer snapshots in typed columns', function () {
    ['job' => $job, 'requiredQuestion' => $textareaQuestion, 'optionalQuestion' => $numberQuestion] = submissionValidationFixture();
    $textQuestion = JobApplicationQuestion::factory()->for($job->company)->for($job)->create([
        'question' => 'Portfolio URL',
        'response_type' => ApplicationQuestionType::Text,
        'required' => true,
    ]);

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($textareaQuestion, [
        'answers' => [
            $textareaQuestion->id => 'Long form answer',
            $numberQuestion->id => '123.50',
            $textQuestion->id => 'https://example.test/portfolio',
        ],
    ]))->assertStatus(303);

    $answers = Application::query()->sole()->answers()->get()->keyBy('job_application_question_id');

    expect($answers[$textareaQuestion->id]->value_text)->toBe('Long form answer')
        ->and($answers[$textareaQuestion->id]->question_snapshot)->toBe('Portfolio summary')
        ->and($answers[$numberQuestion->id]->value_number)->toBe('123.5000')
        ->and($answers[$numberQuestion->id]->value_text)->toBeNull()
        ->and($answers[$textQuestion->id]->value_text)->toBe('https://example.test/portfolio');
});

it('requires a CV and enforces its configured extension, detected MIME, and maximum size', function (array $override) {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, $override))
        ->assertSessionHasErrors('cv');

    expect(Application::query()->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'missing' => [['cv' => null]],
    'prohibited extension' => [[
        'cv' => UploadedFile::fake()->create(
            'resume.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ),
    ]],
    'invalid detected mime' => [[
        'cv' => UploadedFile::fake()->create('resume.pdf', 100, 'text/plain'),
    ]],
    'too large' => [[
        'cv' => UploadedFile::fake()->create('resume.pdf', 10_241, 'application/pdf'),
    ]],
]);

it('requires and validates text cover letters when configured', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture([
        'cover_letter_type' => CoverLetterType::Text,
        'cover_letter_required' => true,
    ]);

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion))
        ->assertSessionHasErrors('cover_letter');

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'cover_letter' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'),
    ]))->assertSessionHasErrors('cover_letter');
});

it('requires and validates file cover letters when configured', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture([
        'cover_letter_type' => CoverLetterType::File,
        'cover_letter_required' => true,
    ]);
    $job->coverLetterFileTypes()->attach(CvFileType::query()->where('extension', 'docx')->sole());

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion))
        ->assertSessionHasErrors('cover_letter');

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'cover_letter' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'),
    ]))->assertSessionHasErrors('cover_letter');
});

it('allows an absent optional cover letter in either mode', function (CoverLetterType $type) {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture([
        'cover_letter_type' => $type,
        'cover_letter_required' => false,
    ]);

    if ($type === CoverLetterType::File) {
        $job->coverLetterFileTypes()->attach(CvFileType::query()->where('extension', 'pdf')->sole());
    }

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion))
        ->assertStatus(303);

    expect(Application::query()->sole()->cover_letter_text)->toBeNull();
})->with(CoverLetterType::cases());

it('validates phone countries, referral UUIDs, and UTM names', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture();

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'phone_country' => 'XX',
        'referral_key' => 'not-a-uuid',
        'utm' => ['source' => 'invalid'],
    ]))->assertSessionHasErrors([
        'phone_country',
        'referral_key',
        'utm.source',
    ]);
});

it('localizes field validation with the job locale', function () {
    ['job' => $job, 'requiredQuestion' => $requiredQuestion] = submissionValidationFixture([
        'application_locale' => ApplicationLocale::BrazilianPortuguese,
    ]);

    $this->post(route('job.apply.store', $job->key), submissionValidationPayload($requiredQuestion, [
        'name' => null,
    ]))->assertSessionHasErrors([
        'name' => 'O campo Nome completo é obrigatório.',
    ]);
});
