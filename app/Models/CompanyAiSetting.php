<?php

namespace App\Models;

use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Enums\Feature;
use Database\Factories\CompanyAiSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $company_id
 * @property AiProvider $provider
 * @property string|null $openai_api_key
 * @property string $model
 * @property AiCredentialStatus $credential_status
 * @property Carbon|null $validated_at
 */
#[Fillable(['company_id', 'provider', 'openai_api_key', 'model', 'credential_status', 'validated_at'])]
#[Hidden(['openai_api_key'])]
class CompanyAiSetting extends Model
{
    /** @use HasFactory<CompanyAiSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'provider' => AiProvider::Platform->value,
        'model' => 'gpt-4o-mini',
        'credential_status' => AiCredentialStatus::NotConfigured->value,
    ];

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'openai_api_key' => 'encrypted',
            'credential_status' => AiCredentialStatus::class,
            'validated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function maskedKey(): ?string
    {
        if (blank($this->openai_api_key)) {
            return null;
        }

        return Str::substr($this->openai_api_key, 0, 3)
            .str_repeat('•', 12)
            .Str::substr($this->openai_api_key, -4);
    }

    public function canUseOwnKey(Company $company): bool
    {
        return $this->company_id === $company->getKey()
            && $company->hasFeature(Feature::OwnAiKey)
            && $this->provider === AiProvider::Own
            && $this->credential_status === AiCredentialStatus::Active
            && filled($this->openai_api_key);
    }
}
