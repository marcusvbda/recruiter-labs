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

        $today = today();

        return Referral::query()
            ->where('key', $key)
            ->whereHas('job', fn (Builder $query): Builder => $query
                ->where('published', true)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today))
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today)))
            ->first();
    }
}
