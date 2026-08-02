<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;

class SubmitJobApplicationData
{
    /**
     * @param  array<int, string|int|float|null>  $answers
     * @param  list<array{name: string, value: string}>  $utmParameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phoneCountry,
        public readonly ?string $phone,
        public readonly UploadedFile $cv,
        public readonly string|UploadedFile|null $coverLetter,
        public readonly array $answers,
        public readonly ?string $referralKey,
        public readonly array $utmParameters,
        public readonly ?string $ipAddress,
    ) {}
}
