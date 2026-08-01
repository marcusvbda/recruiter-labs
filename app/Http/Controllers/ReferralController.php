<?php

namespace App\Http\Controllers;

use App\Enums\PhoneCountry;
use App\Services\ReferralService;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService) {}

    public function show(string $key): Response
    {
        $referral = $this->referralService->retrieve($key);

        abort_if($referral === null, 404);

        $referral->job->description = filled($referral->job->description)
            ? RichContentRenderer::make($referral->job->description)->toHtml()
            : null;

        return Inertia::render('job/apply', [
            'referral' => $referral,
            'job' => $referral->job,
            'phoneCountries' => PhoneCountry::applicationOptions(),
        ]);
    }
}
