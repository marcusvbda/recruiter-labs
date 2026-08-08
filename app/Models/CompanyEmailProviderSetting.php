<?php

namespace App\Models;

use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $company_id
 * @property EmailProvider $provider
 * @property string|null $api_key
 * @property EmailCredentialStatus $credential_status
 * @property Carbon|null $validated_at
 * @property bool $is_default
 */
#[Fillable(['company_id', 'provider', 'api_key', 'credential_status', 'validated_at', 'is_default'])]
#[Hidden(['api_key'])]
class CompanyEmailProviderSetting extends Model
{
    protected $attributes = [
        'credential_status' => EmailCredentialStatus::NotConfigured->value,
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'provider' => EmailProvider::class,
            'api_key' => 'encrypted',
            'credential_status' => EmailCredentialStatus::class,
            'validated_at' => 'datetime',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function maskedKey(): ?string
    {
        if (blank($this->api_key)) {
            return null;
        }

        return Str::substr($this->api_key, 0, 3)
            .str_repeat('•', 12)
            .Str::substr($this->api_key, -4);
    }
}
