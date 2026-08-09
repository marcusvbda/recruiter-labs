<?php

namespace App\Contracts;

use App\Enums\EmailProvider;
use App\Mail\Recruitment\RecruitmentMail;
use App\Models\CompanyEmailProviderSetting;

interface RecruitmentEmailSender
{
    public function provider(): EmailProvider;

    public function isReady(CompanyEmailProviderSetting $providerSetting): bool;

    public function send(
        CompanyEmailProviderSetting $providerSetting,
        RecruitmentMail $mailable,
        string $recipient,
        string $companyName,
        string $idempotencyKey,
    ): void;
}
