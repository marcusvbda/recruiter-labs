<?php

namespace App\Data;

/**
 * The accepted values of one imported row, after every field was judged.
 *
 * A property is null when the cell was blank, absent from the file, or refused:
 * "no information supplied" and "supplied but unusable" look the same here on
 * purpose, because the refusal is already reported as an issue and nothing
 * downstream should try to import a value that failed validation.
 *
 * Values are stored the way the recruiter will see them again: the name keeps
 * its accents and capitalization, the email keeps the case it was typed in, and
 * the phone keeps its leading `+`. Only `$normalizedEmail` is a comparison key,
 * and it is never shown as the candidate's address.
 */
final readonly class CandidateImportFields
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $normalizedEmail = null,
        public ?string $phone = null,
        public ?string $linkedinUrl = null,
        public ?string $cvFilename = null,
        public ?string $receivedOn = null,
        public ?string $sourceLabel = null,
    ) {}

    /** @return array{name: string|null, email: string|null, phone: string|null, linkedin_url: string|null, cv_filename: string|null, received_on: string|null, source_label: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'linkedin_url' => $this->linkedinUrl,
            'cv_filename' => $this->cvFilename,
            'received_on' => $this->receivedOn,
            'source_label' => $this->sourceLabel,
        ];
    }
}
