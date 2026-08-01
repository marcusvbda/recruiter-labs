<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Models\User;
use App\Services\JobService;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function __construct(private readonly JobService $jobService) {}

    public function show(string $key): Response
    {
        $job = $this->jobService->retrieve($key);

        abort_if($job === null, 404);

        $job->description = filled($job->description)
            ? RichContentRenderer::make($job->description)->toHtml()
            : null;

        return Inertia::render('job/apply', [
            'job' => $job,
            'phoneCountries' => PhoneCountry::applicationOptions(),
        ]);
    }

    public function preview(Request $request, string $key): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $job = $this->jobService->retrieveForPreview($key, $user);

        abort_if($job === null, 404);

        $job->description = filled($job->description)
            ? RichContentRenderer::make($job->description)->toHtml()
            : null;

        return Inertia::render('job/apply', [
            'job' => $job,
            'phoneCountries' => PhoneCountry::applicationOptions(),
            'preview' => true,
        ]);
    }
}
