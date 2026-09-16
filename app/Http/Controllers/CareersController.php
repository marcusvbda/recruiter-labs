<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Models\Company;
use App\Models\Job;
use App\Models\Referral;
use App\Services\JobService;
use App\Services\PublicJobPageService;
use App\Services\ReferralService;
use App\Services\UtmParameterExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CareersController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly PublicJobPageService $publicJobPageService,
        private readonly ReferralService $referralService,
        private readonly UtmParameterExtractor $utmParameterExtractor,
    ) {}

    public function show(Request $request, Company $company): Response
    {
        abort_unless($company->careers_enabled, 404);

        $canonicalUrl = route('careers.show', ['company' => $company->slug]);
        $description = $this->metaDescription($company);
        $publicCompany = $this->publicJobPageService->company($company);

        return Inertia::render('careers/show', [
            'company' => $publicCompany,
            'jobs' => $company->jobs()
                ->acceptingApplications()
                ->orderBy('name')
                ->get()
                ->map(fn ($job): array => [
                    ...$this->publicJobPageService->listingJob($job),
                    'url' => $this->jobUrl($company, $job, $request),
                ])
                ->values()
                ->all(),
            'urls' => [
                'current' => $request->fullUrl(),
                'canonical' => $canonicalUrl,
            ],
            'meta' => [
                'title' => "Careers at {$company->name}",
                'description' => $description,
                'canonicalUrl' => $canonicalUrl,
                'openGraph' => [
                    'title' => "Careers at {$company->name}",
                    'description' => $description,
                    'url' => $canonicalUrl,
                    'imageUrl' => $this->publicJobPageService->metadataImageUrl($company),
                ],
                'robots' => 'index,follow',
            ],
        ]);
    }

    public function job(Request $request, Company $company, string $key): Response
    {
        abort_unless($company->careers_enabled, 404);

        $job = $this->jobService->retrieveForCareers($company, $key);

        abort_if($job === null, 404);

        $referral = $this->referral($request, $job);

        if (! $request->session()->pull('skip_application_click_trace', false)) {
            $this->jobService->traceClick($job, $request, $referral);
        }
        App::setLocale((string) $job->getRawOriginal('application_locale'));

        $canonicalUrl = route('careers.jobs.show', [
            'company' => $company->slug,
            'key' => $job->key,
        ]);
        $availability = $this->publicJobPageService->availability($job);
        $description = $this->jobMetaDescription($job, $company);
        $publicCompany = $this->publicJobPageService->company($company);

        return Inertia::render('careers/job', [
            'company' => $publicCompany,
            'job' => $this->publicJobPageService->applicationJob($job),
            ...($referral === null ? [] : ['referral' => ['key' => $referral->key]]),
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
            'availability' => $availability,
            'urls' => [
                'current' => $request->fullUrl(),
                'canonical' => $canonicalUrl,
                'careers' => route('careers.show', ['company' => $company->slug]),
                'application' => $canonicalUrl,
            ],
            'meta' => [
                'title' => "{$job->name} at {$company->name}",
                'description' => $description,
                'canonicalUrl' => $canonicalUrl,
                'openGraph' => [
                    'title' => "{$job->name} at {$company->name}",
                    'description' => $description,
                    'url' => $canonicalUrl,
                    'imageUrl' => $this->publicJobPageService->metadataImageUrl($company),
                ],
                'robots' => $availability['acceptsApplications'] ? 'index,follow' : 'noindex,follow',
            ],
        ]);
    }

    private function metaDescription(Company $company): string
    {
        return filled($company->careers_description)
            ? Str::limit(Str::squish($company->careers_description), 160)
            : "Explore open roles at {$company->name}.";
    }

    private function jobMetaDescription(Job $job, Company $company): string
    {
        $excerpt = $this->publicJobPageService->descriptionExcerpt($job);

        return $excerpt ?? "Explore the {$job->name} opportunity at {$company->name}.";
    }

    private function jobUrl(Company $company, Job $job, Request $request): string
    {
        $utmParameters = collect($this->utmParameterExtractor->extract($request->query()))
            ->pluck('value', 'name')
            ->all();

        return route('careers.jobs.show', [
            'company' => $company->slug,
            'key' => $job->key,
            ...$utmParameters,
        ]);
    }

    private function referral(Request $request, Job $job): ?Referral
    {
        $key = $request->query('referral');

        return is_string($key)
            ? $this->referralService->retrieveForApplication($key, $job)
            : null;
    }
}
