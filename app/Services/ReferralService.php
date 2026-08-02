<?php

namespace App\Services;

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

        return Referral::query()
            ->with([
                'job.company:id,name',
                'job.applicationQuestions:id,job_id,question,response_type,description,required,sort',
                'job.acceptedCvTypes:id,extension,sort',
                'job.coverLetterFileTypes:id,extension,sort',
            ])
            ->where('key', $key)
            ->whereHas('job', fn (Builder $query): Builder => $query
                ->currentlyActive())
            ->first();
    }
}
