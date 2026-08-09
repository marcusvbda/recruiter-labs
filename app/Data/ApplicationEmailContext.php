<?php

namespace App\Data;

use App\Models\Application;

readonly class ApplicationEmailContext implements RecruitmentEmailContext
{
    public function __construct(
        public int $applicationId,
        public string $candidateName,
        public string $candidateEmail,
        public string $jobTitle,
        public string $employerName,
    ) {}

    public static function fromApplication(Application $application): self
    {
        $application->loadMissing(['candidate', 'job', 'company']);

        return new self(
            applicationId: (int) $application->getKey(),
            candidateName: $application->candidate->name,
            candidateEmail: (string) $application->candidate->email,
            jobTitle: $application->job->name,
            employerName: $application->company->name,
        );
    }

    public function recipientEmail(): string
    {
        return $this->candidateEmail;
    }

    public function companyName(): string
    {
        return $this->employerName;
    }

    public function idempotencyKey(): string
    {
        return 'application:'.$this->applicationId;
    }
}
