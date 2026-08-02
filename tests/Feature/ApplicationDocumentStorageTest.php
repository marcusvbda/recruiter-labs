<?php

use App\Actions\SubmitJobApplication;
use App\Data\SubmitJobApplicationData;
use App\Enums\ApplicationDocumentType;
use App\Enums\CoverLetterType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PlanSeeder::class, CvFileTypeSeeder::class]);
    Storage::fake('local');
});

afterEach(function () {
    Event::forget('eloquent.creating: '.ApplicationDocument::class);
});

it('removes every stored document when a later upload step fails and rolls back database records', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'business')->sole()->id,
    ]);
    $job = Job::factory()->for($company)->create([
        'published' => true,
        'cover_letter_type' => CoverLetterType::File,
        'cover_letter_required' => true,
    ]);
    $pdf = CvFileType::query()->where('extension', 'pdf')->sole();
    $job->acceptedCvTypes()->attach($pdf);
    $job->coverLetterFileTypes()->attach($pdf);
    Status::factory()->for($company)->create(['order' => 1]);

    Event::listen(
        'eloquent.creating: '.ApplicationDocument::class,
        function (ApplicationDocument $document): void {
            if ($document->type === ApplicationDocumentType::CoverLetter) {
                throw new RuntimeException('Simulated metadata failure.');
            }
        },
    );

    $data = new SubmitJobApplicationData(
        name: 'Cleanup Candidate',
        email: 'cleanup@example.test',
        phoneCountry: 'IE',
        phone: null,
        cv: UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        coverLetter: UploadedFile::fake()->create('cover-letter.pdf', 100, 'application/pdf'),
        answers: [],
        referralKey: null,
        utmParameters: [],
        ipAddress: '192.0.2.10',
    );

    expect(fn () => app(SubmitJobApplication::class)->run($job, $data))
        ->toThrow(RuntimeException::class, 'Simulated metadata failure.');

    expect(Application::query()->exists())->toBeFalse()
        ->and(Candidate::query()->exists())->toBeFalse()
        ->and(ApplicationDocument::query()->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
