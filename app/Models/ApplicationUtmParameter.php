<?php

namespace App\Models;

use Database\Factories\ApplicationUtmParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'name', 'value'])]
class ApplicationUtmParameter extends Model
{
    /** @use HasFactory<ApplicationUtmParameterFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
