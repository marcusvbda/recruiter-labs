<?php

namespace App\Models;

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['company_id', 'automatable_type', 'automatable_id', 'event_type', 'action_type', 'action_config', 'status_id', 'is_active'])]
class AutomationEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'action_config' => 'array',
            'event_type' => AutomationEventType::class,
            'action_type' => AutomationActionType::class,
            'is_active' => 'bool',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function automatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Resolves the email template referenced inside `action_config`.
     *
     * Not a real Eloquent relation since the foreign key lives inside JSON
     * rather than a dedicated column; used by the Filament layer.
     */
    public function emailTemplate(): ?EmailTemplate
    {
        $emailTemplateId = $this->action_config['email_template_id'] ?? null;

        if ($emailTemplateId === null) {
            return null;
        }

        return EmailTemplate::query()
            ->where('company_id', $this->company_id)
            ->find($emailTemplateId);
    }
}
