<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobClick;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobService
{
    public function __construct(private readonly UtmParameterExtractor $utmParameterExtractor) {}

    public function traceClick(Job $job, Request $request, ?Referral $referral = null): JobClick
    {
        $referralId = $referral?->job_id === $job->getKey()
            && $referral->company_id === $job->company_id
            ? $referral->getKey()
            : null;

        return DB::transaction(function () use ($job, $request, $referralId): JobClick {
            $click = $job->clicks()->create([
                'company_id' => $job->company_id,
                'referral_id' => $referralId,
                'ip_address' => $request->ip(),
            ]);

            $utmParameters = $this->utmParameterExtractor->extract($request->query());

            if ($utmParameters !== []) {
                $click->utmParameters()->createMany($utmParameters);
            }

            return $click;
        });
    }

    public function retrieve(string $key): ?Job
    {
        if (! Str::isUuid($key)) {
            return null;
        }

        return Job::query()
            ->with($this->applicationPageRelations())
            ->where('key', $key)
            ->currentlyActive()
            ->first();
    }

    public function retrieveForPreview(string $key, User $user): ?Job
    {
        if (! Str::isUuid($key)) {
            return null;
        }

        return Job::query()
            ->with($this->applicationPageRelations())
            ->where('key', $key)
            ->whereHas('company.users', fn (Builder $query): Builder => $query->whereKey($user->getKey()))
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function applicationPageRelations(): array
    {
        return [
            'company:id,name',
            'applicationQuestions:id,job_id,question,response_type,description,required,sort',
            'acceptedCvTypes:id,extension,sort',
            'coverLetterFileTypes:id,extension,sort',
        ];
    }
}
