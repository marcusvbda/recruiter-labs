<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Job;

class SubmitCareerJobApplicationRequest extends SubmitJobApplicationRequest
{
    protected function resolveSubmissionJob(string $key): ?Job
    {
        $company = $this->route('company');

        if (! $company instanceof Company || ! $company->careers_enabled) {
            return null;
        }

        return Job::query()
            ->with($this->applicationSubmissionRelations())
            ->whereBelongsTo($company)
            ->where('key', $key)
            ->where('published', true)
            ->first();
    }
}
