<?php

namespace App\Models;

use App\Enums\EmailNotificationType;
use Database\Factories\CompanyEmailNotificationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'notification_type', 'enabled'])]
class CompanyEmailNotificationSetting extends Model
{
    /** @use HasFactory<CompanyEmailNotificationSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => EmailNotificationType::class,
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
