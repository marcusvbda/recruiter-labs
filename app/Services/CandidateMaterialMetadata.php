<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class CandidateMaterialMetadata
{
    /** @return array{source_label: string, received_on: string|null} */
    public function validate(string $sourceLabel, ?string $receivedOn, ?string $timezone = null): array
    {
        $data = ['source_label' => trim($sourceLabel), 'received_on' => filled($receivedOn) ? $receivedOn : null];
        Validator::make($data, [
            'source_label' => ['required', 'string', 'max:120'],
            'received_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now($timezone ?? config('app.timezone'))->toDateString()],
        ])->validate();

        return $data;
    }
}
