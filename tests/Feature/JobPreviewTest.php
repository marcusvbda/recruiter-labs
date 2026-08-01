<?php

use App\Enums\CoverLetterType;
use App\Enums\PhoneCountry;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->seed(CvFileTypeSeeder::class);
});

it('shows an unpublished job preview to a user from its company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($company);
    $job = Job::factory()->for($company)->create([
        'name' => 'Private Preview Job',
        'published' => false,
        'cover_letter_required' => true,
        'cover_letter_type' => CoverLetterType::File,
    ]);
    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));
    $job->coverLetterFileTypes()->sync(CvFileType::query()->whereIn('extension', ['pdf', 'docx'])->pluck('id'));

    $this->actingAs($user)
        ->get(route('job.preview', ['key' => $job->key]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('job/apply')
            ->where('preview', true)
            ->where('job.name', 'Private Preview Job')
            ->where('job.published', false)
            ->where('job.cover_letter_required', true)
            ->where('job.cover_letter_type', CoverLetterType::File->value)
            ->has('job.cover_letter_file_types', 2)
            ->has('phoneCountries', count(PhoneCountry::cases())));
});

it('does not expose a job preview to a user from another company', function () {
    $job = Job::factory()->create(['published' => false]);
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create();
    $otherUser->companies()->attach($otherCompany);

    $this->actingAs($otherUser)
        ->get(route('job.preview', ['key' => $job->key]))
        ->assertNotFound();
});

it('requires authentication for the job preview', function () {
    $job = Job::factory()->create();

    $this->get(route('job.preview', ['key' => $job->key]))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('renders editing and preview tabs on the job edit page', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();

    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->assertSee(__('jobs.edit_tabs.edit'))
        ->assertSee(__('jobs.edit_tabs.preview'))
        ->assertSee(__('jobs.edit_tabs.preview_description'))
        ->assertSeeHtml('<iframe')
        ->assertSeeHtml("job/{$job->key}/preview");
});
