<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService) {}

    public function show(string $key): never
    {
        $referral = $this->referralService->retrieve($key);

        abort_if($referral === null, 404);

        dd($referral);
    }
}
