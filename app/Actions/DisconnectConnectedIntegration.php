<?php

namespace App\Actions;

use App\Enums\ConnectedIntegrationStatus;
use App\Enums\EmailCredentialStatus;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class DisconnectConnectedIntegration
{
    public function __construct(private ConnectedIntegrationRegistry $registry) {}

    public function run(Company $company, User $user, string $pluginKey): void
    {
        Gate::forUser($user)->authorize('update', $company);

        $integration = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', $pluginKey)
            ->firstOrFail();

        $disconnectFailed = false;

        try {
            $this->registry->plugin($pluginKey)->disconnect($integration);
        } catch (Throwable $exception) {
            $disconnectFailed = true;
            Log::warning('Connected integration provider cleanup failed; local credentials were cleared.', [
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'plugin_key' => $pluginKey,
                'exception_class' => $exception::class,
            ]);
        }

        $integration->forceFill([
            'status' => ConnectedIntegrationStatus::Revoked,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'last_error_at' => $disconnectFailed ? now() : null,
        ])->save();

        CompanyEmailProviderSetting::query()
            ->where('company_id', $company->getKey())
            ->where('connected_integration_id', $integration->getKey())
            ->update([
                'credential_status' => EmailCredentialStatus::NotConfigured->value,
                'validated_at' => null,
                'is_default' => false,
            ]);
    }
}
