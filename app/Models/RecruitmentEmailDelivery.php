<?php

namespace App\Models;

use App\Enums\EmailProvider;
use App\Enums\RecruitmentEmailDeliveryStatus;
use Database\Factories\RecruitmentEmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RecruitmentEmailDeliveryStatus $status
 * @property int $attempts
 */
#[Fillable(['company_id', 'provider_setting_id', 'provider', 'idempotency_key', 'status', 'attempts', 'provider_message_id', 'last_exception_class', 'last_attempted_at', 'delivered_at'])]
class RecruitmentEmailDelivery extends Model
{
    /** @use HasFactory<RecruitmentEmailDeliveryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider' => EmailProvider::class,
            'status' => RecruitmentEmailDeliveryStatus::class,
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CompanyEmailProviderSetting, $this> */
    public function providerSetting(): BelongsTo
    {
        return $this->belongsTo(CompanyEmailProviderSetting::class, 'provider_setting_id');
    }
}
