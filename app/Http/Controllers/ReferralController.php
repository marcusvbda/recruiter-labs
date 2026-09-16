<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Models\Job;
use App\Services\JobService;
use App\Services\PublicJobPageService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
        private readonly JobService $jobService,
        private readonly PublicJobPageService $publicJobPageService,
    ) {}

    public function show(Request $request, string $key): Response
    {
        $referral = $this->referralService->retrieve($key);

        abort_if($referral === null, 404);

        $job = $referral->job;

        abort_unless($job instanceof Job, 404);

        if (! $request->session()->pull('skip_application_click_trace', false)) {
            $this->jobService->traceClick($job, $request, $referral);
        }
        App::setLocale((string) $job->getRawOriginal('application_locale'));

        return Inertia::render('job/apply', [
            'referral' => $referral,
            'job' => $this->publicJobPageService->applicationJob($job),
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
            'availability' => $this->publicJobPageService->availability($job),
            'urls' => [
                'current' => $request->fullUrl(),
                'canonical' => route('job.show', ['key' => $job->key]),
                'application' => route('job.apply.store', ['key' => $job->key]),
            ],
        ]);
    }
}
