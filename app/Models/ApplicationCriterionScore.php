<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $application_id
 * @property string $criterion
 * @property int $weight
 * @property int $score
 * @property string $reason
 */
#[Fillable(['company_id', 'application_id', 'criterion', 'weight', 'score', 'reason'])]
class ApplicationCriterionScore extends Model
{
    protected $table = 'application_criterion_scores';

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'score' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
