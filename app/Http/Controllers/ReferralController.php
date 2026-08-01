<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService) {}

    public function show(string $key): Response
    {
        $referral = $this->referralService->retrieve($key);

        abort_if($referral === null, 404);

        return Inertia::render('job/apply', ['referral' => $referral, 'job' => $referral->job]);
    }
}
