<?php

namespace App\Actions;

use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailProviderConfigurationEventType;
use App\Models\Company;
use App\Models\CompanyAuditLog;
use App\Models\CompanyEmailProviderSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateCompanyEmailProviderSettings
{
    public function run(
        Company $company,
        User $changedBy,
        EmailProvider $provider,
        ?string $apiKey = null,
    ): CompanyEmailProviderSetting {
        Gate::forUser($changedBy)->authorize('update', $company);

        if ($apiKey !== null && (blank($apiKey) || Str::length($apiKey) > 512)) {
            throw new InvalidArgumentException('The email provider API key is invalid.');
        }

        return DB::transaction(function () use ($company, $changedBy, $provider, $apiKey): CompanyEmailProviderSetting {
            $setting = CompanyEmailProviderSetting::query()->firstOrNew([
                'company_id' => $company->getKey(),
                'provider' => $provider->value,
            ]);
            $isNewRow = ! $setting->exists;
            $hadKey = filled($setting->api_key);

            if ($apiKey !== null) {
                $setting->api_key = $apiKey;
                $setting->credential_status = EmailCredentialStatus::PendingValidation;
                $setting->validated_at = null;
            }

            if ($isNewRow) {
                $hasAnyProvider = CompanyEmailProviderSetting::query()
                    ->where('company_id', $company->getKey())
                    ->exists();

                $setting->is_default = ! $hasAnyProvider;
            }

            $setting->save();

            if ($apiKey !== null) {
                $this->audit(
                    $company,
                    $changedBy,
                    $hadKey ? EmailProviderConfigurationEventType::CredentialReplaced : EmailProviderConfigurationEventType::CredentialAdded,
                    ['provider' => $provider->value],
                );
            }

            return $setting;
        });
    }

    /** @param array<string, mixed> $metadata */
    private function audit(
        Company $company,
        User $user,
        EmailProviderConfigurationEventType $event,
        array $metadata = [],
    ): void {
        CompanyAuditLog::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'event' => $event->value,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
