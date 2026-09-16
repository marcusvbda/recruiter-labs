<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicJobPageService
{
    /**
     * @return array{
     *     name: string,
     *     description: string|null,
     *     logoUrl: string|null
     * }
     */
    public function company(Company $company): array
    {
        return [
            'name' => $company->name,
            'description' => $company->careers_description,
            'logoUrl' => filled($company->careers_logo_path)
                ? Storage::disk('public')->url($company->careers_logo_path)
                : null,
        ];
    }

    public function metadataImageUrl(Company $company): string
    {
        return filled($company->careers_logo_path)
            ? Storage::disk('public')->url($company->careers_logo_path)
            : asset('assets/image/logo.png');
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     application_locale: string,
     *     description: string|null,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     cover_letter_required: bool,
     *     cover_letter_type: string,
     *     application_questions: array<int, array{id: int, question: string, response_type: string, description: string|null, required: bool, sort: int}>,
     *     accepted_cv_types: array<int, array{id: int, extension: string, sort: int}>,
     *     cover_letter_file_types: array<int, array{id: int, extension: string, sort: int}>,
     *     company: array{name: string}
     * }
     */
    public function applicationJob(Job $job): array
    {
        $company = $job->company;

        return [
            'key' => $job->key,
            'name' => $job->name,
            'application_locale' => $job->application_locale->value,
            'description' => $this->descriptionHtml($job),
            'starts_at' => $job->starts_at?->toDateString(),
            'ends_at' => $job->ends_at?->toDateString(),
            'cover_letter_required' => $job->cover_letter_required,
            'cover_letter_type' => $job->cover_letter_type->value,
            'application_questions' => $job->applicationQuestions
                ->map(static fn (JobApplicationQuestion $question): array => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'response_type' => $question->response_type->value,
                    'description' => $question->description,
                    'required' => $question->required,
                    'sort' => $question->sort,
                ])
                ->values()
                ->all(),
            'accepted_cv_types' => $job->acceptedCvTypes
                ->map(static fn (CvFileType $type): array => [
                    'id' => (int) $type->getKey(),
                    'extension' => (string) $type->extension,
                    'sort' => (int) $type->sort,
                ])
                ->values()
                ->all(),
            'cover_letter_file_types' => $job->coverLetterFileTypes
                ->map(static fn (CvFileType $type): array => [
                    'id' => (int) $type->getKey(),
                    'extension' => (string) $type->extension,
                    'sort' => (int) $type->sort,
                ])
                ->values()
                ->all(),
            'company' => [
                'name' => $company->name,
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, descriptionExcerpt: string|null, endsAt: string|null}
     */
    public function listingJob(Job $job): array
    {
        return [
            'key' => $job->key,
            'name' => $job->name,
            'descriptionExcerpt' => $this->descriptionExcerpt($job),
            'endsAt' => $job->ends_at?->toDateString(),
        ];
    }

    /** @return array{status: 'open'|'unavailable', acceptsApplications: bool, message: string|null} */
    public function availability(Job $job): array
    {
        $acceptsApplications = $job->acceptsApplications();

        return [
            'status' => $acceptsApplications ? 'open' : 'unavailable',
            'acceptsApplications' => $acceptsApplications,
            'message' => $acceptsApplications
                ? null
                : 'Applications are no longer being accepted for this role.',
        ];
    }

    public function descriptionExcerpt(Job $job): ?string
    {
        $description = $this->descriptionHtml($job);

        return filled($description)
            ? Str::limit(Str::squish(strip_tags($description)), 180)
            : null;
    }

    private function descriptionHtml(Job $job): ?string
    {
        return filled($job->description)
            ? RichContentRenderer::make($job->description)->toHtml()
            : null;
    }
}
