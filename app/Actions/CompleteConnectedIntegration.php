<?php

namespace App\Actions;

use App\Data\OAuthTokenData;
use App\Enums\ConnectedIntegrationStatus;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class CompleteConnectedIntegration
{
    public function run(Company $company, User $user, string $pluginKey, OAuthTokenData $token): ConnectedIntegration
    {
        Gate::forUser($user)->authorize('update', $company);

        return DB::transaction(function () use ($company, $user, $pluginKey, $token): ConnectedIntegration {
            $integration = ConnectedIntegration::query()->firstOrNew([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'plugin_key' => $pluginKey,
            ]);

            $sameExternalAccount = $integration->exists
                && filled($integration->external_account_id)
                && ($token->externalAccountId === null || $token->externalAccountId === $integration->external_account_id);
            $refreshToken = $token->refreshToken ?? ($sameExternalAccount ? $integration->refresh_token : null);

            if (blank($refreshToken)) {
                throw new RuntimeException('The provider did not return an offline refresh token.');
            }

            $integration->fill([
                'status' => ConnectedIntegrationStatus::Connected,
                'external_account_id' => $token->externalAccountId ?? $integration->external_account_id,
                'account_email' => $token->accountEmail ?? $integration->account_email,
                'account_name' => $token->accountName ?? $integration->account_name,
                'access_token' => $token->accessToken,
                'refresh_token' => $refreshToken,
                'granted_scopes' => $token->scopes,
                'metadata' => $token->metadata,
                'expires_at' => $token->expiresAt === null ? null : now()->setTimestamp($token->expiresAt),
                'connected_at' => now(),
                'last_refreshed_at' => null,
                'last_error_at' => null,
            ])->save();

            return $integration;
        });
    }
}
