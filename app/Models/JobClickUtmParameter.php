<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_click_id', 'name', 'value'])]
class JobClickUtmParameter extends Model
{
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<JobClick, $this> */
    public function jobClick(): BelongsTo
    {
        return $this->belongsTo(JobClick::class);
    }
}
