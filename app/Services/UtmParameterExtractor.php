<?php

namespace App\Services;

use Illuminate\Support\Str;

class UtmParameterExtractor
{
    /**
     * @param  array<string, mixed>  ...$sources
     * @return list<array{name: string, value: string}>
     */
    public function extract(array ...$sources): array
    {
        $parameters = [];

        foreach ($sources as $source) {
            foreach ($source as $name => $value) {
                $normalizedName = Str::lower((string) $name);

                if (
                    ! preg_match('/^utm_[a-z0-9_]+$/', $normalizedName)
                    || ! is_scalar($value)
                    || blank((string) $value)
                ) {
                    continue;
                }

                $parameters[$normalizedName] = [
                    'name' => Str::limit($normalizedName, 100, ''),
                    'value' => Str::limit((string) $value, 255, ''),
                ];
            }
        }

        return array_slice(array_values($parameters), 0, 20);
    }
}
