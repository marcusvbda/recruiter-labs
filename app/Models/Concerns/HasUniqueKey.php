<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Populates a model's `key` attribute with a unique UUID on creation.
 *
 * The `key` column is intended for public-facing identifiers (e.g. URLs)
 * and must never be exposed as a mass-assignable attribute or as an
 * editable form field — it is auto-generated on create and is never
 * settable via mass assignment. This trait only hooks the `creating` event;
 * it does not prevent a direct, explicit assignment
 * (`$model->key = ...; $model->save();`) after the record exists.
 */
trait HasUniqueKey
{
    protected static function bootHasUniqueKey(): void
    {
        static::creating(function (self $model): void {
            if (! $model->key) {
                $model->key = (string) Str::uuid();
            }
        });
    }
}
