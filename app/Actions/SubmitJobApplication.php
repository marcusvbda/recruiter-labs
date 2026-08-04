<?php

namespace App\Actions;

use App\Data\SubmitJobApplicationData;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationDocumentType;
use App\Enums\ApplicationQuestionType;
use App\Enums\ApplicationSource;
use App\Enums\CoverLetterType;
use App\Enums\PhoneCountry;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\Referral;
use App\Services\ApplicationAvailabilityService;
use App\Services\ApplicationDocumentStorage;
use App\Services\ReferralService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitJobApplication
{
    public function __construct(
        private readonly ApplicationAvailabilityService $availabilityService,
        private readonly ApplicationDocumentStorage $documentStorage,
        private readonly ReferralService $referralService,
    ) {}

    public function run(Job $job, SubmitJobApplicationData $data): Application
    {
        /** @var list<ApplicationDocument> $storedDocuments */
        $storedDocuments = [];

        try {
            return DB::transaction(function () use ($job, $data, &$storedDocuments): Application {
                $job = $this->availabilityService->lockAndEnsureCanReceive($job);
                $job->load('applicationQuestions');
                $referral = $this->resolveReferral($job, $data->referralKey);
                $candidate = $this->resolveCandidate($job, $data);

                if ($job->applications()->whereBelongsTo($candidate)->exists()) {
                    $this->throwDuplicateApplication();
                }

                $coverLetterType = $this->coverLetterSubmissionType($job, $data);
                $application = Application::query()->create([
                    'company_id' => $job->company_id,
                    'job_id' => $job->getKey(),
                    'candidate_id' => $candidate->getKey(),
                    'status_id' => $this->availabilityService->initialStatus($job)->getKey(),
                    'referral_id' => $referral?->getKey(),
                    'source' => $referral === null ? ApplicationSource::Direct : ApplicationSource::Referral,
                    'analysis_status' => ApplicationAnalysisStatus::Pending,
                    'cover_letter_type' => $coverLetterType,
                    'cover_letter_text' => $coverLetterType === ApplicationCoverLetterType::Text
                        ? Str::trim((string) $data->coverLetter)
                        : null,
                    'submitted_ip' => $data->ipAddress,
                ]);

                $this->persistAnswers($application, $job, $data->answers);
                $application->utmParameters()->createMany($data->utmParameters);

                $storedDocuments[] = $this->documentStorage->store(
                    $application,
                    $data->cv,
                    ApplicationDocumentType::Cv,
                );

                if ($coverLetterType === ApplicationCoverLetterType::File && $data->coverLetter instanceof UploadedFile) {
                    $storedDocuments[] = $this->documentStorage->store(
                        $application,
                        $data->coverLetter,
                        ApplicationDocumentType::CoverLetter,
                    );
                }

                return $application->load([
                    'candidate',
                    'status',
                    'referral',
                    'answers',
                    'documents',
                    'utmParameters',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->documentStorage->deleteStored($storedDocuments);
            $this->throwDuplicateApplication();
        } catch (Throwable $exception) {
            $this->documentStorage->deleteStored($storedDocuments);

            throw $exception;
        }
    }

    private function resolveCandidate(Job $job, SubmitJobApplicationData $data): Candidate
    {
        $email = Str::lower(Str::trim($data->email));
        $phone = PhoneCountry::from($data->phoneCountry)->toInternational($data->phone);
        $candidate = Candidate::query()
            ->where('company_id', $job->company_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->lockForUpdate()
            ->first();

        if (! $candidate instanceof Candidate) {
            return Candidate::query()->create([
                'company_id' => $job->company_id,
                'name' => Str::squish($data->name),
                'email' => $email,
                'phone' => $phone,
                'socials' => [],
            ]);
        }

        $updates = ['email' => $email];

        if (blank($candidate->name)) {
            $updates['name'] = Str::squish($data->name);
        }

        if (blank($candidate->phone) && filled($phone)) {
            $updates['phone'] = $phone;
        }

        $candidate->update($updates);

        return $candidate;
    }

    private function resolveReferral(Job $job, ?string $referralKey): ?Referral
    {
        if ($referralKey === null) {
            return null;
        }

        $referral = $this->referralService->retrieveForApplication($referralKey, $job);

        if (! $referral instanceof Referral) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.general'),
            ]);
        }

        return $referral;
    }

    private function coverLetterSubmissionType(
        Job $job,
        SubmitJobApplicationData $data,
    ): ApplicationCoverLetterType {
        if ($data->coverLetter === null || (is_string($data->coverLetter) && blank($data->coverLetter))) {
            return ApplicationCoverLetterType::None;
        }

        return $job->getRawOriginal('cover_letter_type') === CoverLetterType::File->value
            ? ApplicationCoverLetterType::File
            : ApplicationCoverLetterType::Text;
    }

    /** @param array<int, string|int|float|null> $answers */
    private function persistAnswers(Application $application, Job $job, array $answers): void
    {
        $questionIds = $job->applicationQuestions->modelKeys();
        $submittedQuestionIds = array_map('intval', array_keys($answers));

        if (array_diff($submittedQuestionIds, $questionIds) !== []) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.general'),
            ]);
        }

        foreach ($job->applicationQuestions as $question) {
            $value = Arr::get($answers, (string) $question->getKey());

            if (blank($value) && $value !== 0 && $value !== '0') {
                continue;
            }

            $responseType = ApplicationQuestionType::from(
                (string) $question->getRawOriginal('response_type'),
            );

            $application->answers()->create([
                'company_id' => $application->company_id,
                'job_application_question_id' => $question->getKey(),
                'question_snapshot' => $question->question,
                'response_type' => $responseType,
                'value_text' => $responseType === ApplicationQuestionType::Number
                    ? null
                    : (string) $value,
                'value_number' => $responseType === ApplicationQuestionType::Number
                    ? $value
                    : null,
            ]);
        }
    }

    private function throwDuplicateApplication(): never
    {
        throw ValidationException::withMessages([
            'email' => __('job_application.errors.duplicate'),
            '_form' => __('job_application.errors.duplicate'),
        ]);
    }
}
