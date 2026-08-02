<?php

namespace App\Http\Controllers;

use App\Actions\SubmitJobApplication;
use App\Http\Requests\SubmitJobApplicationRequest;
use App\Services\UtmParameterExtractor;
use Illuminate\Http\RedirectResponse;

class JobApplicationController extends Controller
{
    public function __construct(
        private readonly SubmitJobApplication $submitJobApplication,
        private readonly UtmParameterExtractor $utmParameterExtractor,
    ) {}

    public function store(SubmitJobApplicationRequest $request, string $key): RedirectResponse
    {
        abort_unless($request->job()->key === $key, 404);

        $application = $this->submitJobApplication->run(
            $request->job(),
            $request->toData($this->utmParameterExtractor),
        );

        return back(status: 303)->with([
            'application_submitted' => true,
            'application_id' => $application->getKey(),
            'skip_application_click_trace' => true,
        ]);
    }
}
