<?php

use App\Enums\ApplicationLocale;
use App\Enums\ApplicationQuestionType;
use App\Enums\CoverLetterType;
use App\Enums\JobCriteriaProcessingStatus;
use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\JobCriterion;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->seed(CvFileTypeSeeder::class);
});

it('uses a rich editor without file uploads for the job description', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $page = Livewire::test(CreateJob::class);
    $descriptionField = $page->instance()
        ->getSchema('form')
        ?->getComponent('description', withHidden: true);

    expect($descriptionField)->toBeInstanceOf(RichEditor::class);

    /** @var RichEditor $descriptionField */
    expect($descriptionField->hasFileAttachments())->toBeFalse()
        ->and($descriptionField->hasToolbarButton('attachFiles'))->toBeFalse();
});

it('stacks the required response toggle beside the response type field', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $page = Livewire::test(CreateJob::class);
    $questionsRepeater = $page->instance()
        ->getSchema('form')
        ?->getComponent('applicationQuestions', withHidden: true);

    expect($questionsRepeater)->toBeInstanceOf(Repeater::class);

    /** @var Repeater $questionsRepeater */
    $requiredToggle = $questionsRepeater->getChildSchema()
        ?->getComponent('required', withHidden: true);

    expect($requiredToggle)->toBeInstanceOf(Toggle::class);

    /** @var Toggle $requiredToggle */
    expect($requiredToggle->isInline())->toBeFalse();
});

it('changes the published status from the job form', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Published Job',
            'published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $job = Job::query()->where('name', 'Published Job')->sole();

    expect($job->published)->toBeTrue();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'published' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($job->fresh()->published)->toBeFalse();
});

it('changes the application page language from the job form', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Localized Job',
            'application_locale' => ApplicationLocale::BrazilianPortuguese->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $job = Job::query()->where('name', 'Localized Job')->sole();

    expect($job->application_locale)->toBe(ApplicationLocale::BrazilianPortuguese);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'application_locale' => ApplicationLocale::Spanish->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($job->fresh()->application_locale)->toBe(ApplicationLocale::Spanish);
});

it('configures whether a job accepts applications and its individual limit', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));

    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'applications_paused' => true,
            'application_limit' => 75,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($job->fresh()->applications_paused)->toBeTrue()
        ->and($job->fresh()->application_limit)->toBe(75);
});

it('rejects a non-positive individual application limit', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));

    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'application_limit' => 0,
        ])
        ->call('save')
        ->assertHasFormErrors(['application_limit']);
});

it('rejects an unsupported application page language', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Unsupported Locale Job',
            'application_locale' => 'fr',
        ])
        ->call('create')
        ->assertHasFormErrors(['application_locale']);
});

it('creates a job with campaign fields and application questions', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $acceptedCvTypeIds = CvFileType::query()
        ->whereIn('extension', ['pdf', 'docx'])
        ->pluck('id')
        ->all();

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Senior Backend Engineer',
            'application_locale' => ApplicationLocale::English->value,
            'description' => 'We are hiring for a senior backend role.',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-09-01',
            'campaign_expectation' => 'Expect to hire 2 engineers meeting at least 80% of criteria.',
            'acceptedCvTypes' => $acceptedCvTypeIds,
            'cover_letter_type' => CoverLetterType::File->value,
            'cover_letter_required' => true,
            'coverLetterFileTypes' => $acceptedCvTypeIds,
            'applicationQuestions' => [
                [
                    'question' => 'What is your preferred name?',
                    'response_type' => 'text',
                    'description' => 'Use the name you would like us to use during the process.',
                    'required' => true,
                ],
                [
                    'question' => 'How many years of Laravel experience do you have?',
                    'response_type' => 'number',
                    'description' => null,
                    'required' => true,
                ],
                [
                    'question' => 'Tell us about a challenging project.',
                    'response_type' => 'textarea',
                    'description' => 'Keep your answer concise.',
                    'required' => false,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $job = Job::query()->where('name', 'Senior Backend Engineer')->sole();

    expect($job->company_id)->toBe($company->id)
        ->and($job->application_locale)->toBe(ApplicationLocale::English)
        ->and($job->description)->toBe('<p>We are hiring for a senior backend role.</p>')
        ->and($job->starts_at->toDateString())->toBe('2026-08-01')
        ->and($job->ends_at->toDateString())->toBe('2026-09-01')
        ->and($job->campaign_expectation)->toBe('Expect to hire 2 engineers meeting at least 80% of criteria.');

    expect($job->cover_letter_type)->toBe(CoverLetterType::File)
        ->and($job->cover_letter_required)->toBeTrue();

    expect($job->acceptedCvTypes()->orderBy('sort')->pluck('extension')->all())
        ->toBe(['pdf', 'docx']);

    expect($job->coverLetterFileTypes()->orderBy('sort')->pluck('extension')->all())
        ->toBe(['pdf', 'docx']);

    $applicationQuestions = $job->applicationQuestions()->get();

    expect($applicationQuestions)->toHaveCount(3)
        ->and($applicationQuestions[0]->company_id)->toBe($company->id)
        ->and($applicationQuestions[0]->question)->toBe('What is your preferred name?')
        ->and($applicationQuestions[0]->response_type)->toBe(ApplicationQuestionType::Text)
        ->and($applicationQuestions[0]->description)->toBe('Use the name you would like us to use during the process.')
        ->and($applicationQuestions[0]->required)->toBeTrue()
        ->and($applicationQuestions[0]->sort)->toBe(1)
        ->and($applicationQuestions[1]->response_type)->toBe(ApplicationQuestionType::Number)
        ->and($applicationQuestions[1]->sort)->toBe(2)
        ->and($applicationQuestions[2]->response_type)->toBe(ApplicationQuestionType::Textarea)
        ->and($applicationQuestions[2]->description)->toBe('Keep your answer concise.')
        ->and($applicationQuestions[2]->required)->toBeFalse()
        ->and($applicationQuestions[2]->sort)->toBe(3);

});

it('requires a supported CV format and valid response field types', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Security Engineer',
            'acceptedCvTypes' => [],
            'applicationQuestions' => [
                [
                    'question' => 'Upload another document',
                    'response_type' => 'file',
                    'description' => null,
                    'required' => true,
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors([
            'acceptedCvTypes',
            'applicationQuestions.0.response_type',
        ]);
});

it('requires an accepted cover letter format when file upload is selected', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Platform Engineer',
            'acceptedCvTypes' => CvFileType::query()->pluck('id')->all(),
            'cover_letter_type' => CoverLetterType::File->value,
            'coverLetterFileTypes' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['coverLetterFileTypes']);
});

it('shows the AI analysis state while criteria are processing', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Processing,
    ]);
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertSee(__('jobs.criteria.processing_title'))
        ->assertSee(__('jobs.criteria.status_processing'))
        ->assertDontSee(__('jobs.criteria.section_title'));
});

it('shows an honest terminal state for jobs created before AI criteria analysis', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly([
        'criteria_processing_status' => JobCriteriaProcessingStatus::NotStarted,
    ]);
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertSee(__('jobs.criteria.not_started_title'))
        ->assertDontSee(__('jobs.criteria.section_title'));
});

it('rejects invalid AI criteria fields', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
    ]);
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->fillForm([
            'jobCriteria' => [
                ['criterion' => str_repeat('a', 151), 'weight' => 15, 'reason' => ''],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors([
            'jobCriteria.0.criterion' => 'max',
            'jobCriteria.0.weight',
            'jobCriteria.0.reason' => 'required',
        ]);
});

it('adds job criteria rows to an existing job through the edit form', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
    ]);
    $generationBeforeManualEdit = $job->refresh()->criteria_generation;
    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));
    actAsCompany($company);
    Queue::fake();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->fillForm([
            'jobCriteria' => [
                [
                    'criterion' => 'Leadership',
                    'weight' => 7,
                    'reason' => 'The role requires leading a multidisciplinary team.',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pivotRow = JobCriterion::query()->where('job_id', $job->id)->sole();

    expect($pivotRow->company_id)->toBe($company->id)
        ->and($pivotRow->criterion)->toBe('Leadership')
        ->and($pivotRow->weight)->toBe(7)
        ->and($pivotRow->reason)->toBe('The role requires leading a multidisciplinary team.')
        ->and($job->fresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed)
        ->and($job->fresh()->criteria_generation)->toBe($generationBeforeManualEdit + 1);

    Queue::assertNotPushed(AnalyzeJobCriteria::class);
});

it('does not queue a new analysis when accepted CV formats change', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create([
        'description' => '<p>Job description.</p>',
    ]);
    $job->updateQuietly([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
    ]);

    $acceptedCvTypeIds = CvFileType::query()->orderBy('sort')->pluck('id');
    $job->acceptedCvTypes()->sync([$acceptedCvTypeIds->first()]);

    actAsCompany($company);
    Queue::fake();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'acceptedCvTypes' => $acceptedCvTypeIds->all(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($job->fresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed);

    Queue::assertNothingPushed();
});

it('persists edits made on the editing tab even when the AI Criteria tab is active on save', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create(['name' => 'Original Name']);
    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->fillForm(['name' => 'Updated Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($job->fresh()->name)->toBe('Updated Name');
});

it('does not invalidate the criteria generation when saving while an analysis is not completed', function (JobCriteriaProcessingStatus $status) {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->acceptedCvTypes()->sync(CvFileType::query()->pluck('id'));
    $job->updateQuietly(['criteria_processing_status' => $status]);
    $generationBeforeSave = $job->refresh()->criteria_generation;
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($job->fresh()->criteria_processing_status)->toBe($status)
        ->and($job->fresh()->criteria_generation)->toBe($generationBeforeSave);
})->with([
    'not started' => [JobCriteriaProcessingStatus::NotStarted],
    'pending' => [JobCriteriaProcessingStatus::Pending],
    'processing' => [JobCriteriaProcessingStatus::Processing],
    'failed' => [JobCriteriaProcessingStatus::Failed],
]);
