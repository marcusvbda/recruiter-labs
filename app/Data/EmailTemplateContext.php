<?php

namespace App\Data;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Status;
use App\Services\EmailTemplateRenderer;

/**
 * Everything a recruiter email template is allowed to know about.
 *
 * A template is written once and reused in different situations, so the values
 * behind its tokens cannot assume a full {@see Application}: the same template
 * may be sent from a candidate profile with no job in play. Every part is
 * therefore optional, and a token whose part is absent simply resolves to an
 * empty string (see {@see EmailTemplateRenderer}).
 */
readonly class EmailTemplateContext
{
    public function __construct(
        public ?Candidate $candidate = null,
        public ?Job $job = null,
        public ?Company $company = null,
        public ?Application $application = null,
        public ?Status $status = null,
    ) {}

    /**
     * The richest context there is: an application already carries the
     * candidate, the job, the company and the current stage.
     */
    public static function forApplication(Application $application): self
    {
        $application->loadMissing(['candidate', 'job', 'company', 'status']);

        return new self(
            candidate: $application->candidate,
            job: $application->job,
            company: $application->company,
            application: $application,
            status: $application->status,
        );
    }

    /**
     * A candidate-first context: used when messaging someone directly, with a
     * job attached only when the conversation is about one.
     */
    public static function forCandidate(Candidate $candidate, ?Job $job = null, ?Company $company = null): self
    {
        $company ??= $job === null ? $candidate->company : $job->company;

        return new self(
            candidate: $candidate,
            job: $job,
            company: $company,
        );
    }
}
