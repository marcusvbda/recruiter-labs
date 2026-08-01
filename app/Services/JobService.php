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

            $utmParameters = $this->extractUtmParameters($request);

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

        $today = today();

        return Job::query()
            ->with($this->applicationPageRelations())
            ->where('key', $key)
            ->where('published', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('starts_at')
                ->orWhereDate('starts_at', '<=', $today))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ends_at')
                ->orWhereDate('ends_at', '>=', $today))
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

    /**
     * @return list<array{name: string, value: string}>
     */
    private function extractUtmParameters(Request $request): array
    {
        $parameters = [];

        foreach ($request->query() as $name => $value) {
            $normalizedName = Str::lower((string) $name);

            if (
                count($parameters) >= 20
                || ! preg_match('/^utm_[a-z0-9_]+$/', $normalizedName)
                || ! is_scalar($value)
                || blank((string) $value)
            ) {
                continue;
            }

            $parameters[] = [
                'name' => Str::limit($normalizedName, 100, ''),
                'value' => Str::limit((string) $value, 255, ''),
            ];
        }

        return $parameters;
    }
}
