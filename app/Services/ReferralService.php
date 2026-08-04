<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ReferralService
{
    public function retrieve(string $key): ?Referral
    {
        if (! Str::isUuid($key)) {
            return null;
        }

        $referral = $this->availableQuery()
            ->with([
                'job.company:id,name',
                'job.applicationQuestions:id,job_id,question,response_type,description,required,sort',
                'job.acceptedCvTypes:id,extension,sort',
                'job.coverLetterFileTypes:id,extension,sort',
            ])
            ->where('key', $key)
            ->first();

        return $this->hasApplicationCapacity($referral) ? $referral : null;
    }

    public function retrieveForApplication(string $key, Job $job): ?Referral
    {
        if (! Str::isUuid($key)) {
            return null;
        }

        $referral = $this->availableQuery()
            ->where('key', $key)
            ->where('company_id', $job->company_id)
            ->where('job_id', $job->getKey())
            ->lockForUpdate()
            ->first();

        return $this->hasApplicationCapacity($referral) ? $referral : null;
    }

    /** @return Builder<Referral> */
    private function availableQuery(): Builder
    {
        return Referral::query()
            ->where('published', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->whereHas('job', fn (Builder $query): Builder => $query->currentlyActive());
    }

    private function hasApplicationCapacity(?Referral $referral): bool
    {
        return $referral instanceof Referral
            && $referral->applications()->count() < $referral->max_applications;
    }
}
