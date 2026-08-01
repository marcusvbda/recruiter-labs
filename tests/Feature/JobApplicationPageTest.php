<?php

use App\Enums\ApplicationLocale;
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
            ->where('job.application_locale', ApplicationLocale::English->value)
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
            ->has('phoneCountries', count(PhoneCountry::cases()))
            ->where('translations.locale', 'en-US')
            ->where('translations.form.full_name', 'Full name'));
});

it('uses the language configured on the job for all fixed application page copy', function (
    ApplicationLocale $applicationLocale,
    string $browserLocale,
    string $fullNameLabel,
    string $applyButton,
) {
    $job = Job::factory()->create([
        'application_locale' => $applicationLocale,
        'published' => true,
    ]);

    $this->get(route('job.show', ['key' => $job->key]))
        ->assertSuccessful()
        ->assertSee('<html lang="'.str_replace('_', '-', $applicationLocale->value).'"', false)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.application_locale', $applicationLocale->value)
            ->where('translations.locale', $browserLocale)
            ->where('translations.form.full_name', $fullNameLabel)
            ->where('translations.hero.apply_for_role', $applyButton));
})->with([
    'English' => [ApplicationLocale::English, 'en-US', 'Full name', 'Apply for this role'],
    'Portuguese' => [ApplicationLocale::BrazilianPortuguese, 'pt-BR', 'Nome completo', 'Candidatar-se à vaga'],
    'Spanish' => [ApplicationLocale::Spanish, 'es-ES', 'Nombre completo', 'Postularme al empleo'],
]);
