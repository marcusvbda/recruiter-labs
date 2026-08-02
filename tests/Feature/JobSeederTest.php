<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationQuestionType;
use App\Enums\CoverLetterType;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds a complete full stack engineer job for local development', function () {
    $this->seed();

    $job = Job::query()->where('name', 'Senior Full Stack Engineer')->sole();
    $admin = User::query()->where('email', 'admin@user.com')->sole();

    expect($job->company->name)->toBe('Gravity Labs')
        ->and($job->company->slug)->toBe('gravity-labs')
        ->and($admin->companies()->whereKey($job->company)->exists())->toBeTrue()
        ->and($job->published)->toBeTrue()
        ->and($job->cover_letter_required)->toBeTrue()
        ->and($job->cover_letter_type)->toBe(CoverLetterType::Text)
        ->and($job->starts_at->isPast())->toBeTrue()
        ->and($job->ends_at->isFuture())->toBeTrue()
        ->and($job->description)->toContain('Laravel, React, TypeScript, and PostgreSQL')
        ->and($job->campaign_expectation)->toContain('Hire one senior full stack engineer')
        ->and($job->clicks)->toHaveCount(12)
        ->and($job->applications)->toHaveCount(8)
        ->and($job->applications()->whereHas('status', fn (Builder $query): Builder => $query->where('is_hired', true))->count())->toBe(1);

    expect($job->acceptedCvTypes()->orderBy('sort')->pluck('extension')->all())
        ->toBe(['pdf', 'doc', 'docx']);

    expect($job->coverLetterFileTypes()->orderBy('sort')->pluck('extension')->all())
        ->toBe(['pdf', 'doc', 'docx']);

    $questions = $job->applicationQuestions()->get();

    expect($questions)->toHaveCount(4)
        ->and($questions->pluck('sort')->all())->toBe([1, 2, 3, 4])
        ->and($questions[0]->response_type)->toBe(ApplicationQuestionType::Text)
        ->and($questions[1]->response_type)->toBe(ApplicationQuestionType::Number)
        ->and($questions[2]->response_type)->toBe(ApplicationQuestionType::Textarea)
        ->and($questions[3]->required)->toBeFalse()
        ->and($job->jobCriteria)->toHaveCount(5)
        ->and($job->jobCriteria->max('weight'))->toBe(10);

    $applications = $job->applications()->with(['answers', 'documents', 'referral'])->get();
    $textCoverLetter = $applications->firstWhere('cover_letter_type', ApplicationCoverLetterType::Text);
    $fileCoverLetter = $applications->firstWhere('cover_letter_type', ApplicationCoverLetterType::File);
    $referredApplication = $applications->firstWhere('referral_id', '!==', null);

    expect($applications->every(fn ($application): bool => $application->analysis_status === ApplicationAnalysisStatus::Pending))->toBeTrue()
        ->and($applications->every(fn ($application): bool => $application->answers->count() === 4))->toBeTrue()
        ->and($applications->every(fn ($application): bool => $application->documents->isNotEmpty()))->toBeTrue()
        ->and($textCoverLetter)->not->toBeNull()
        ->and($textCoverLetter?->cover_letter_text)->toContain('Gravity Labs')
        ->and($fileCoverLetter?->documents)->toHaveCount(2)
        ->and($referredApplication?->referral)->not->toBeNull();

    foreach ($applications->flatMap->documents as $document) {
        Storage::disk($document->disk)->assertExists($document->path);
    }

    $this->seed();

    $seededJob = Job::query()->where('name', 'Senior Full Stack Engineer')->sole();
    $seededApplications = $seededJob->applications()->with('answers')->get();

    expect(Job::query()->where('name', 'Senior Full Stack Engineer')->count())->toBe(1)
        ->and($seededJob->applicationQuestions)->toHaveCount(4)
        ->and($seededJob->jobCriteria)->toHaveCount(5)
        ->and($seededApplications)->toHaveCount(8)
        ->and($seededApplications->every(fn ($application): bool => $application->answers->count() === 4))->toBeTrue()
        ->and($seededApplications->flatMap->answers)->toHaveCount(32);
});
