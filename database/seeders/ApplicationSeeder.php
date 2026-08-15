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

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'gravity-labs')->first();

        if ($company instanceof Company) {
            $this->seedDemoApplications($company);
            $this->seedInternalApplications($company);
        }
    }

    /**
     * A handful of candidates on the non-default pipeline, so the two workflows
     * can be compared side by side on the Kanban board.
     */
    private function seedInternalApplications(Company $company): void
    {
        $job = Job::query()
            ->whereBelongsTo($company)
            ->where('name', 'Engineering Team Lead (Internal)')
            ->first();

        if (! $job instanceof Job) {
            return;
        }

        $employees = [
            ['name' => 'Rita Almeida', 'email' => 'rita.almeida@gravitylabs.test', 'status' => 'Identified'],
            ['name' => 'Tom Hughes', 'email' => 'tom.hughes@gravitylabs.test', 'status' => 'Manager Review'],
            ['name' => 'Yuki Tanaka', 'email' => 'yuki.tanaka@gravitylabs.test', 'status' => 'Internal Interview'],
        ];

        foreach ($employees as $index => $employee) {
            $candidate = Candidate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => $employee['email'],
                ],
                [
                    'name' => $employee['name'],
                    'phone' => '+353 85 900 '.(1000 + $index),
                    'socials' => [],
                ],
            );

            $status = Status::query()
                ->where('pipeline_id', $job->pipeline_id)
                ->where('name', $employee['status'])
                ->firstOrFail();

            Application::query()->updateOrCreate(
                [
                    'job_id' => $job->id,
                    'candidate_id' => $candidate->id,
                ],
                [
                    'company_id' => $company->id,
                    'status_id' => $status->id,
                    'source' => ApplicationSource::Direct,
                    'analysis_status' => ApplicationAnalysisStatus::Pending,
                    'cover_letter_type' => ApplicationCoverLetterType::None,
                    'submitted_ip' => '198.51.100.'.(80 + $index),
                ],
            );
        }
    }

    private function seedDemoApplications(Company $company): void
    {
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
            ['name' => 'Lucas Ferreira', 'email' => 'lucas.ferreira@example.test', 'phone' => '+55 21 98765-4321', 'status' => 'Technical Interview'],
            ['name' => 'Maya Patel', 'email' => 'maya.patel@example.test', 'phone' => '+44 7700 900123', 'status' => 'Technical Interview'],
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

            // Statuses are resolved from the job's own pipeline, never from the
            // company at large.
            $status = Status::query()
                ->where('pipeline_id', $job->pipeline_id)
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
