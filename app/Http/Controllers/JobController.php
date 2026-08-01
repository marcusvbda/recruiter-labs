<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Models\User;
use App\Services\JobService;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function __construct(private readonly JobService $jobService) {}

    public function show(Request $request, string $key): Response
    {
        $job = $this->jobService->retrieve($key);

        abort_if($job === null, 404);

        $this->jobService->traceClick($job, $request);
        App::setLocale($job->application_locale->value);

        $job->description = filled($job->description)
            ? RichContentRenderer::make($job->description)->toHtml()
            : null;

        return Inertia::render('job/apply', [
            'job' => $job,
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
        ]);
    }

    public function preview(Request $request, string $key): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $job = $this->jobService->retrieveForPreview($key, $user);

        abort_if($job === null, 404);

        App::setLocale($job->application_locale->value);

        $job->description = filled($job->description)
            ? RichContentRenderer::make($job->description)->toHtml()
            : null;

        return Inertia::render('job/apply', [
            'job' => $job,
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'translations' => __('job_application'),
            'preview' => true,
        ]);
    }
}
