<?php

namespace App\Http\Controllers;

use App\Services\JobService;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function __construct(private readonly JobService $jobService) {}

    public function show(string $key): Response
    {
        $job = $this->jobService->retrieve($key);

        abort_if($job === null, 404);

        return Inertia::render('job/apply', compact('job'));
    }
}
