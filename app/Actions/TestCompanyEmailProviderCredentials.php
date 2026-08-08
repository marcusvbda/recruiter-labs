<?php

namespace App\Actions;

use App\Data\EmailCredentialTestResultData;
use App\Enums\EmailProvider;
use App\Enums\EmailProviderConfigurationEventType;
use App\Models\Company;
use App\Models\CompanyAuditLog;
use App\Models\CompanyEmailProviderSetting;
use App\Models\User;
use App\Services\ResendCredentialTester;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class TestCompanyEmailProviderCredentials
{
    public function __construct(private readonly ResendCredentialTester $tester) {}

    public function run(Company $company, User $changedBy, EmailProvider $provider): EmailCredentialTestResultData
    {
        Gate::forUser($changedBy)->authorize('update', $company);

        $setting = CompanyEmailProviderSetting::query()
            ->where('company_id', $company->getKey())
            ->where('provider', $provider->value)
            ->first();

        if ($setting === null || blank($setting->api_key)) {
            throw new InvalidArgumentException('No email provider API key is configured for this workspace.');
        }

        $result = $this->tester->test($setting->api_key);
        $setting->update([
            'credential_status' => $result->status,
            'validated_at' => now(),
        ]);

        CompanyAuditLog::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $changedBy->getKey(),
            'event' => $result->success
                ? EmailProviderConfigurationEventType::CredentialTestSucceeded->value
                : EmailProviderConfigurationEventType::CredentialTestFailed->value,
            'metadata' => ['provider' => $provider->value],
        ]);

        return $result;
    }
}
