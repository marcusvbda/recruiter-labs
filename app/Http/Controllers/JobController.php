<?php

namespace App\Http\Controllers;

use App\Services\JobService;

class JobController extends Controller
{
    public function __construct(private readonly JobService $jobService) {}

    public function show(string $key): never
    {
        $job = $this->jobService->retrieve($key);

        abort_if($job === null, 404);

        dd($job);
    }
}
