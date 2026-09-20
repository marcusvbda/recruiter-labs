<?php

namespace App\Models;

use App\Services\EmailTemplateRenderer;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

/**
 * A reusable recruiter-authored email: written once in Settings, then resolved
 * against a candidate/job/application context wherever a message is sent.
 *
 * The template is workspace-owned, and its content is never interpreted beyond
 * the fixed `{{ token }}` catalog of {@see EmailTemplateRenderer}.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $is_available
 */
#[Fillable(['company_id', 'name', 'subject', 'body', 'is_available'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected $attributes = [
        'is_available' => true,
    ];

    protected static function booted(): void
    {
        // Realtime refresh instead of polling — see EmailTemplatesTable::socket().
        static::saved(function (EmailTemplate $template): void {
            RealtimeEvent::dispatch('email_templates_'.$template->company->slug, 'EmailTemplateUpdated');
        });

        static::deleted(function (EmailTemplate $template): void {
            RealtimeEvent::dispatch('email_templates_'.$template->company->slug, 'EmailTemplateUpdated');
        });
    }

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The templates a recruiter may still pick when sending something.
     *
     * @param  Builder<EmailTemplate>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true);
    }
}
