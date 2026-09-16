<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Models\User;
use App\Services\JobService;
use App\Services\PublicJobPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly PublicJobPageService $publicJobPageService,
    ) {}

    public function show(Request $request, string $key): Response
    {
        $job = $this->jobService->retrieve($key);

        abort_if($job === null, 404);

        if (! $request->session()->pull('skip_application_click_trace', false)) {
            $this->jobService->traceClick($job, $request);
        }
        App::setLocale((string) $job->getRawOriginal('application_locale'));

        $availability = $this->publicJobPageService->availability($job);
        $publicCompany = $this->publicJobPageService->company($job->company);
        $canonicalUrl = $job->company?->careers_enabled && $availability['acceptsApplications']
            ? route('careers.jobs.show', ['company' => $job->company->slug, 'key' => $job->key])
            : route('job.show', ['key' => $job->key]);
        $description = $this->publicJobPageService->descriptionExcerpt($job)
            ?? "Explore the {$job->name} opportunity at {$publicCompany['name']}.";

        return Inertia::render('job/apply', [
            'job' => $this->publicJobPageService->applicationJob($job),
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
            'availability' => $availability,
            'urls' => [
                'current' => $request->fullUrl(),
                'canonical' => $canonicalUrl,
                'application' => route('job.apply.store', ['key' => $job->key]),
            ],
            'meta' => [
                'title' => "{$job->name} at {$publicCompany['name']}",
                'description' => $description,
                'canonicalUrl' => $canonicalUrl,
                'openGraph' => [
                    'title' => "{$job->name} at {$publicCompany['name']}",
                    'description' => $description,
                    'url' => $canonicalUrl,
                    'imageUrl' => $this->publicJobPageService->metadataImageUrl($job->company),
                ],
                'robots' => $availability['acceptsApplications'] ? 'index,follow' : 'noindex,follow',
            ],
        ]);
    }

    public function preview(Request $request, string $key): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $job = $this->jobService->retrieveForPreview($key, $user);

        abort_if($job === null, 404);

        App::setLocale((string) $job->getRawOriginal('application_locale'));

        return Inertia::render('job/apply', [
            'job' => $this->publicJobPageService->applicationJob($job),
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
            'preview' => true,
            'availability' => [
                'status' => 'open',
                'acceptsApplications' => false,
                'message' => 'Preview mode. Applications cannot be submitted from this page.',
            ],
            'urls' => [
                'current' => $request->fullUrl(),
                'canonical' => route('job.preview', ['key' => $job->key]),
                'application' => route('job.apply.store', ['key' => $job->key]),
            ],
        ]);
    }
}
