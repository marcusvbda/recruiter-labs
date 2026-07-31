<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'name'])]
class Job extends Model
{
    use HasFactory, HasUniqueKey;

    // Not `jobs`: that table name is reserved by Laravel's queue system and is
    // unrelated to this model (also unrelated to any `App\Jobs\*` queue job class).
    protected $table = 'job_postings';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
