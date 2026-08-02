<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationDocumentType;
use App\Enums\ApplicationLocale;
use App\Enums\ApplicationQuestionType;
use App\Enums\ApplicationSource;
use App\Enums\CoverLetterType;
use App\Enums\Limit;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\Status;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PlanSeeder::class, CvFileTypeSeeder::class]);
    Storage::fake('local');
});

/** @return array{company: Company, job: Job, status: Status, question: JobApplicationQuestion} */
function publicSubmissionFixture(array $jobAttributes = []): array
{
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'business')->sole()->id,
    ]);
    $job = Job::factory()->for($company)->create([
        'published' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        ...$jobAttributes,
    ]);
    $job->acceptedCvTypes()->attach(CvFileType::query()->where('extension', 'pdf')->sole());
    $status = Status::factory()->for($company)->create(['order' => 20]);
    Status::factory()->for($company)->create(['order' => 30]);
    $question = JobApplicationQuestion::factory()->for($company)->for($job)->create([
        'question' => 'Years of experience?',
        'response_type' => ApplicationQuestionType::Number,
        'required' => true,
    ]);

    return compact('company', 'job', 'status', 'question');
}

/** @return array<string, mixed> */
function validPublicSubmissionPayload(JobApplicationQuestion $question, array $overrides = []): array
{
    return [
        'name' => '  Public   Candidate ',
        'email' => 'PUBLIC.CANDIDATE@EXAMPLE.TEST',
        'phone_country' => 'BR',
        'phone' => '(11) 99876-5432',
        'cv' => UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
        'cover_letter' => null,
        'answers' => [$question->id => '7'],
        'utm' => [
            'utm_campaign' => 'full-stack-hiring',
        ],
        ...$overrides,
    ];
}

it('submits a complete public application with private documents, answers, referral, and utms', function () {
    ['company' => $company, 'job' => $job, 'status' => $status, 'question' => $question] = publicSubmissionFixture();
    $earliestStatus = Status::factory()->for($company)->create(['order' => 1]);
    $referral = Referral::factory()->for($company)->for($job)->create();
    $url = route('job.apply.store', ['key' => $job->key, 'utm_source' => 'linkedin']);

    $response = $this->from(route('job.show', $job->key))->post($url, validPublicSubmissionPayload($question, [
        'referral_key' => $referral->key,
    ]));

    $response->assertStatus(303)
        ->assertSessionHas('application_submitted', true);

    $application = Application::query()->sole();
    $candidate = $application->candidate;
    $answer = $application->answers()->sole();
    $document = $application->documents()->sole();

    expect($application->company_id)->toBe($company->id)
        ->and($application->job_id)->toBe($job->id)
        ->and($application->status_id)->toBe($earliestStatus->id)
        ->and($application->status_id)->not->toBe($status->id)
        ->and($application->referral_id)->toBe($referral->id)
        ->and($application->source)->toBe(ApplicationSource::Referral)
        ->and($application->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->cover_letter_type)->toBe(ApplicationCoverLetterType::None)
        ->and($candidate->name)->toBe('Public Candidate')
        ->and($candidate->email)->toBe('public.candidate@example.test')
        ->and($candidate->phone)->toBe('+5511998765432')
        ->and($answer->job_application_question_id)->toBe($question->id)
        ->and($answer->question_snapshot)->toBe('Years of experience?')
        ->and($answer->response_type)->toBe(ApplicationQuestionType::Number)
        ->and($answer->value_number)->toBe('7.0000')
        ->and($document->type)->toBe(ApplicationDocumentType::Cv)
        ->and($document->disk)->toBe('local')
        ->and($document->original_name)->toBe('resume.pdf')
        ->and($document->extension)->toBe('pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->checksum)->toHaveLength(64)
        ->and($application->utmParameters()->pluck('value', 'name')->all())->toMatchArray([
            'utm_source' => 'linkedin',
            'utm_campaign' => 'full-stack-hiring',
        ]);

    Storage::disk('local')->assertExists($document->path);
    expect(Storage::disk('public')->exists($document->path))->toBeFalse();
});

it('reuses a candidate only within the job company and preserves administrative fields', function () {
    ['company' => $company, 'job' => $job, 'question' => $question] = publicSubmissionFixture();
    $otherCompany = Company::factory()->create();
    $otherTenantCandidate = Candidate::factory()->for($otherCompany)->create([
        'email' => 'public.candidate@example.test',
    ]);
    $candidate = Candidate::factory()->for($company)->create([
        'name' => 'Administrative Name',
        'email' => 'Public.Candidate@Example.Test',
        'phone' => '+353851112222',
        'socials' => ['linkedin' => 'https://linkedin.example/admin'],
    ]);

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertStatus(303);

    $candidate->refresh();

    expect(Candidate::query()->count())->toBe(2)
        ->and(Application::query()->sole()->candidate_id)->toBe($candidate->id)
        ->and($candidate->name)->toBe('Administrative Name')
        ->and($candidate->email)->toBe('public.candidate@example.test')
        ->and($candidate->phone)->toBe('+353851112222')
        ->and($candidate->socials)->toBe(['linkedin' => 'https://linkedin.example/admin'])
        ->and($otherTenantCandidate->fresh()->applications()->exists())->toBeFalse();
});

it('canonicalizes candidate emails and enforces company email uniqueness in the database', function () {
    $company = Company::factory()->create();
    $candidate = Candidate::factory()->for($company)->create([
        'email' => '  Mixed.Case@Example.Test ',
    ]);

    expect($candidate->email)->toBe('mixed.case@example.test')
        ->and(fn () => Candidate::query()->insert([
            'company_id' => $company->id,
            'name' => 'Duplicate Candidate',
            'email' => 'mixed.case@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
});

it('rejects duplicate applications before storing another document', function () {
    ['company' => $company, 'job' => $job, 'status' => $status, 'question' => $question] = publicSubmissionFixture();
    $candidate = Candidate::factory()->for($company)->create(['email' => 'public.candidate@example.test']);
    Application::factory()->for($company)->for($job)->for($candidate)->create(['status_id' => $status->id]);

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertSessionHasErrors(['email', '_form']);

    expect(Application::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects a company without an initial pipeline status', function () {
    ['job' => $job, 'question' => $question] = publicSubmissionFixture();
    Status::query()->delete();

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertSessionHasErrors('_form');

    expect(Application::query()->exists())->toBeFalse()
        ->and(Candidate::query()->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects unavailable jobs', function (array $attributes) {
    ['job' => $job, 'question' => $question] = publicSubmissionFixture($attributes);

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertSessionHasErrors('_form');

    expect(Application::query()->exists())->toBeFalse();
})->with([
    'unpublished' => [['published' => false]],
    'paused' => [['applications_paused' => true]],
    'before start' => [['starts_at' => now()->addDay(), 'ends_at' => null]],
    'after end' => [['starts_at' => null, 'ends_at' => now()->subDay()]],
]);

it('returns not found for malformed and unknown job keys', function () {
    $this->post('/job/not-a-uuid/apply')->assertNotFound();
    $this->post('/job/00000000-0000-4000-8000-000000000000/apply')->assertNotFound();
});

it('enforces both monthly plan and individual job application limits', function (string $limitType) {
    ['company' => $company, 'job' => $job, 'status' => $status, 'question' => $question] = publicSubmissionFixture();
    $existingCandidate = Candidate::factory()->for($company)->create();
    Application::factory()->for($company)->for($job)->for($existingCandidate)->create(['status_id' => $status->id]);

    if ($limitType === 'plan') {
        $plan = $company->plan;
        $plan->update(['limits' => [...$plan->limits, Limit::Applications->value => 1]]);
    } else {
        $job->update(['application_limit' => 1]);
    }

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertSessionHasErrors('_form');

    expect(Application::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['plan', 'job']);

it('rejects an explicit referral from another job or tenant', function (string $mismatch) {
    ['company' => $company, 'job' => $job, 'question' => $question] = publicSubmissionFixture();

    if ($mismatch === 'job') {
        $otherJob = Job::factory()->for($company)->create();
        $referral = Referral::factory()->for($company)->for($otherJob)->create();
    } else {
        $otherCompany = Company::factory()->create();
        $referral = Referral::factory()->for($otherCompany)->create();
    }

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question, [
        'referral_key' => $referral->key,
    ]))->assertSessionHasErrors('_form');

    expect(Application::query()->exists())->toBeFalse();
})->with(['job', 'tenant']);

it('persists text and file cover letters according to the job configuration', function (string $type) {
    ['job' => $job, 'question' => $question] = publicSubmissionFixture([
        'cover_letter_type' => $type,
        'cover_letter_required' => true,
    ]);

    if ($type === CoverLetterType::File->value) {
        $job->coverLetterFileTypes()->attach(CvFileType::query()->where('extension', 'pdf')->sole());
    }

    $coverLetter = $type === CoverLetterType::Text->value
        ? '  I would love to contribute to this team.  '
        : UploadedFile::fake()->create('cover-letter.pdf', 80, 'application/pdf');

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question, [
        'cover_letter' => $coverLetter,
    ]))->assertStatus(303);

    $application = Application::query()->sole();

    if ($type === CoverLetterType::Text->value) {
        expect($application->cover_letter_type)->toBe(ApplicationCoverLetterType::Text)
            ->and($application->cover_letter_text)->toBe('I would love to contribute to this team.')
            ->and($application->documents()->count())->toBe(1);
    } else {
        expect($application->cover_letter_type)->toBe(ApplicationCoverLetterType::File)
            ->and($application->cover_letter_text)->toBeNull()
            ->and($application->documents()->count())->toBe(2);
    }
})->with([CoverLetterType::Text->value, CoverLetterType::File->value]);

it('uses the job locale for domain errors', function (ApplicationLocale $locale, string $message) {
    ['job' => $job, 'question' => $question] = publicSubmissionFixture([
        'application_locale' => $locale,
        'published' => false,
    ]);

    $this->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertSessionHasErrors(['_form' => $message]);
})->with([
    'Brazilian Portuguese' => [ApplicationLocale::BrazilianPortuguese, 'Esta vaga não está mais aceitando candidaturas.'],
    'Spanish' => [ApplicationLocale::Spanish, 'Este empleo ya no acepta postulaciones.'],
]);

it('does not trace a second click when following the successful submission redirect', function () {
    ['job' => $job, 'question' => $question] = publicSubmissionFixture();
    $showUrl = route('job.show', $job->key);

    $this->get($showUrl)->assertSuccessful();
    expect($job->clicks()->count())->toBe(1);

    $this->from($showUrl)
        ->post(route('job.apply.store', $job->key), validPublicSubmissionPayload($question))
        ->assertRedirect($showUrl);
    $this->get($showUrl)->assertSuccessful();

    expect($job->clicks()->count())->toBe(1);
});
