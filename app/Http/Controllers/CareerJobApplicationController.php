<?php

namespace App\Http\Controllers;

use App\Actions\SubmitJobApplication;
use App\Enums\ApplicationSource;
use App\Http\Requests\SubmitCareerJobApplicationRequest;
use App\Services\UtmParameterExtractor;
use Illuminate\Http\RedirectResponse;

class CareerJobApplicationController extends Controller
{
    public function __construct(
        private readonly SubmitJobApplication $submitJobApplication,
        private readonly UtmParameterExtractor $utmParameterExtractor,
    ) {}

    public function store(SubmitCareerJobApplicationRequest $request): RedirectResponse
    {
        $application = $this->submitJobApplication->run(
            $request->job(),
            $request->toData($this->utmParameterExtractor, ApplicationSource::CareerPage),
        );

        return back(status: 303)->with([
            'application_submitted' => true,
            'application_id' => $application->getKey(),
            'skip_application_click_trace' => true,
        ]);
    }
}
