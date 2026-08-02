<?php

namespace App\Models;

use App\Enums\ApplicationDocumentType;
use Database\Factories\ApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'application_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'extension', 'size', 'checksum', 'uploaded_at'])]
class ApplicationDocument extends Model
{
    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory;

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'type' => ApplicationDocumentType::class,
            'size' => 'integer',
            'uploaded_at' => 'immutable_datetime',
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
