<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Models\Job;
use App\Services\JobService;
use App\Services\ReferralService;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
        private readonly JobService $jobService,
    ) {}

    public function show(Request $request, string $key): Response
    {
        $referral = $this->referralService->retrieve($key);

        abort_if($referral === null, 404);

        $job = $referral->job;

        abort_unless($job instanceof Job, 404);

        $this->jobService->traceClick($job, $request, $referral);
        App::setLocale($job->application_locale->value);

        $job->description = filled($job->description)
            ? RichContentRenderer::make($job->description)->toHtml()
            : null;

        return Inertia::render('job/apply', [
            'referral' => $referral,
            'job' => $job,
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
        ]);
    }
}
