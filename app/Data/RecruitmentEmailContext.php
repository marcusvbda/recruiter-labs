<?php

namespace App\Data;

interface RecruitmentEmailContext
{
    public function recipientEmail(): string;

    public function companyName(): string;

    public function idempotencyKey(): string;
}
