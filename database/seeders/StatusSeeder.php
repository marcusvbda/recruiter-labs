<?php

namespace Database\Seeders;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationDocumentType;
use App\Enums\ApplicationQuestionType;
use App\Enums\ApplicationSource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class StatusSeeder extends Seeder
{
    /** @var list<array{name: string, color: string, order: int, is_hired: bool}> */
    private const DEFAULT_STATUSES = [
        ['name' => 'Applied', 'color' => '#3b82f6', 'order' => 1, 'is_hired' => false],
        ['name' => 'Screening', 'color' => '#f59e0b', 'order' => 2, 'is_hired' => false],
        ['name' => 'Interview', 'color' => '#8b5cf6', 'order' => 3, 'is_hired' => false],
        ['name' => 'Offer', 'color' => '#06b6d4', 'order' => 4, 'is_hired' => false],
        ['name' => 'Hired', 'color' => '#22c55e', 'order' => 5, 'is_hired' => true],
        ['name' => 'Rejected', 'color' => '#ef4444', 'order' => 6, 'is_hired' => false],
    ];

    public function run(): void
    {
        Company::query()
            ->select(['id', 'slug'])
            ->eachById(function (Company $company): void {
                foreach (self::DEFAULT_STATUSES as $status) {
                    $createdStatus = Status::query()->firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'name' => $status['name'],
                        ],
                        [
                            'color' => $status['color'],
                            'order' => $status['order'],
                            'is_hired' => $status['is_hired'],
                        ],
                    );

                    if ($status['is_hired'] && ! $createdStatus->is_hired) {
                        $createdStatus->update(['is_hired' => true]);
                    }
                }

                $this->seedDemoApplications($company);
            });
    }

    private function seedDemoApplications(Company $company): void
    {
        if ($company->slug !== 'gravity-labs') {
            return;
        }

        $job = Job::query()
            ->whereBelongsTo($company)
            ->where('name', 'Senior Full Stack Engineer')
            ->first();

        if (! $job) {
            return;
        }

        $candidates = [
            ['name' => 'Sofia Martins', 'email' => 'sofia.martins@example.test', 'phone' => '+353 85 123 4567', 'status' => 'Applied'],
            ['name' => 'Daniel Silva', 'email' => 'daniel.silva@example.test', 'phone' => '+55 11 99876-5432', 'status' => 'Applied'],
            ['name' => 'Emma Walsh', 'email' => 'emma.walsh@example.test', 'phone' => '+353 87 234 5678', 'status' => 'Screening'],
            ['name' => 'Lucas Ferreira', 'email' => 'lucas.ferreira@example.test', 'phone' => '+55 21 98765-4321', 'status' => 'Interview'],
            ['name' => 'Maya Patel', 'email' => 'maya.patel@example.test', 'phone' => '+44 7700 900123', 'status' => 'Interview'],
            ['name' => 'Noah Bennett', 'email' => 'noah.bennett@example.test', 'phone' => '+1 415 555 0198', 'status' => 'Offer'],
            ['name' => 'Ana Costa', 'email' => 'ana.costa@example.test', 'phone' => '+351 912 345 678', 'status' => 'Hired'],
            ['name' => 'Oliver Jones', 'email' => 'oliver.jones@example.test', 'phone' => '+44 7700 900456', 'status' => 'Rejected'],
        ];

        $referral = $job->company->referrals()->where('job_id', $job->id)->first();

        foreach ($candidates as $index => $candidateData) {
            $candidate = Candidate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => $candidateData['email'],
                ],
                [
                    'name' => $candidateData['name'],
                    'phone' => $candidateData['phone'],
                    'socials' => [],
                ],
            );

            $status = Status::query()
                ->whereBelongsTo($company)
                ->where('name', $candidateData['status'])
                ->firstOrFail();

            $hasTextCoverLetter = $index === 0;
            $hasFileCoverLetter = $index === 2;
            $usesReferral = $index === 1 && $referral !== null;
            $application = Application::query()->updateOrCreate(
                [
                    'job_id' => $job->id,
                    'candidate_id' => $candidate->id,
                ],
                [
                    'company_id' => $company->id,
                    'status_id' => $status->id,
                    'referral_id' => $usesReferral ? $referral->id : null,
                    'source' => $usesReferral ? ApplicationSource::Referral : ApplicationSource::Direct,
                    'analysis_status' => ApplicationAnalysisStatus::Pending,
                    'cover_letter_type' => match (true) {
                        $hasTextCoverLetter => ApplicationCoverLetterType::Text,
                        $hasFileCoverLetter => ApplicationCoverLetterType::File,
                        default => ApplicationCoverLetterType::None,
                    },
                    'cover_letter_text' => $hasTextCoverLetter
                        ? 'I am excited about the opportunity to build thoughtful recruiting products with Gravity Labs.'
                        : null,
                    'submitted_ip' => '198.51.100.'.($index + 40),
                ],
            );

            $this->seedDemoAnswers($application, $job, $index);
            $this->seedDemoDocument($application, ApplicationDocumentType::Cv, 'resume.pdf');

            if ($hasFileCoverLetter) {
                $this->seedDemoDocument($application, ApplicationDocumentType::CoverLetter, 'cover-letter.pdf');
            }

            $application->utmParameters()->updateOrCreate(
                ['name' => 'utm_source'],
                ['value' => $usesReferral ? 'employee-referral' : ($index % 2 === 0 ? 'linkedin' : 'careers-page')],
            );
        }
    }

    private function seedDemoAnswers(Application $application, Job $job, int $index): void
    {
        foreach ($job->applicationQuestions as $question) {
            $responseType = ApplicationQuestionType::from(
                (string) $question->getRawOriginal('response_type'),
            );
            $value = match ($responseType) {
                ApplicationQuestionType::Number => (string) (4 + $index),
                ApplicationQuestionType::Textarea => 'I enjoy shipping maintainable products with collaborative teams and clear customer outcomes.',
                ApplicationQuestionType::Text => $question->required
                    ? $application->candidate->name
                    : 'https://github.com/example-candidate',
            };

            $application->answers()->updateOrCreate(
                ['job_application_question_id' => $question->id],
                [
                    'company_id' => $application->company_id,
                    'question_snapshot' => $question->question,
                    'response_type' => $responseType,
                    'value_text' => $responseType === ApplicationQuestionType::Number ? null : $value,
                    'value_number' => $responseType === ApplicationQuestionType::Number ? $value : null,
                ],
            );
        }
    }

    private function seedDemoDocument(
        Application $application,
        ApplicationDocumentType $type,
        string $originalName,
    ): void {
        $disk = (string) config('filesystems.application_documents_disk', 'local');
        $path = "companies/{$application->company_id}/applications/{$application->id}/{$type->value}/demo.pdf";
        $contents = "%PDF-1.4\nRecruiter Labs demo {$type->value} for application {$application->id}.\n%%EOF";

        Storage::disk($disk)->put($path, $contents);

        ApplicationDocument::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'type' => $type,
            ],
            [
                'company_id' => $application->company_id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size' => mb_strlen($contents, '8bit'),
                'checksum' => hash('sha256', $contents),
                'uploaded_at' => now()->subDays(8 - $application->id),
            ],
        );
    }
}
