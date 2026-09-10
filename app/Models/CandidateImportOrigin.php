<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $company_id
 * @property int $candidate_id
 * @property int|null $batch_id
 * @property int|null $added_by_id
 * @property string $source_label
 * @property CarbonImmutable $added_at
 */
#[Fillable(['company_id', 'candidate_id', 'batch_id', 'added_by_id', 'source_label', 'added_at'])]
class CandidateImportOrigin extends Model
{
    protected static function booted(): void
    {
        static::updating(function (CandidateImportOrigin $origin): void {
            if ($origin->isDirty(['company_id', 'candidate_id', 'batch_id', 'added_by_id', 'added_at', 'source_label'])) {
                throw new LogicException('Candidate first-entry provenance is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'added_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** @return BelongsTo<CandidateImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CandidateImportBatch::class, 'batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
