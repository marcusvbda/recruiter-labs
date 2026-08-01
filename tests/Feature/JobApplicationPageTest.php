<?php

use App\Enums\PhoneCountry;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->seed(CvFileTypeSeeder::class);
});

it('shows the public application page with the job application data', function () {
    $company = Company::factory()->create(['name' => 'Gravity Labs']);
    $job = Job::factory()->for($company)->create([
        'name' => 'Senior Full Stack Engineer',
        'description' => '<h2>Build excellent products</h2><p>Use Laravel and React.</p><script>alert("unsafe")</script>',
        'starts_at' => today()->subDay(),
        'ends_at' => today()->addMonth(),
        'published' => true,
    ]);

    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));
    $job->coverLetterFileTypes()->sync(CvFileType::query()->where('extension', 'pdf')->pluck('id'));
    $job->applicationQuestions()->create([
        'company_id' => $company->id,
        'question' => 'Why do you want to join us?',
        'response_type' => 'textarea',
        'description' => 'Keep your answer concise.',
        'required' => true,
        'sort' => 1,
    ]);

    $this->get(route('job.show', ['key' => $job->key]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('job/apply')
            ->where('job.name', 'Senior Full Stack Engineer')
            ->where('job.company.name', 'Gravity Labs')
            ->where('job.description', fn (string $description): bool => str_contains($description, '<h2>Build excellent products</h2>')
                && str_contains($description, '<p>Use Laravel and React.</p>')
                && ! str_contains($description, '<script>'))
            ->has('job.accepted_cv_types', 3)
            ->where('job.accepted_cv_types.0.extension', 'pdf')
            ->where('job.cover_letter_type', 'text')
            ->where('job.cover_letter_required', false)
            ->has('job.cover_letter_file_types', 1)
            ->has('job.application_questions', 1)
            ->where('job.application_questions.0.question', 'Why do you want to join us?')
            ->where('job.application_questions.0.response_type', 'textarea')
            ->has('phoneCountries', count(PhoneCountry::cases())));
});
