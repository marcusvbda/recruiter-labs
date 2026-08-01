<?php

namespace App\Services;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class JobService
{
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
}
