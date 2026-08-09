<?php

namespace App\Services;

use App\Enums\ConnectedIntegrationStatus;
use App\Exceptions\ConnectedIntegrationReauthorizationRequired;
use App\Exceptions\OAuthRefreshTokenRejected;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ConnectedIntegrationTokenManager
{
    public function __construct(private ConnectedIntegrationRegistry $registry) {}

    public function accessToken(Company $company, User $user, string $pluginKey): string
    {
        Gate::forUser($user)->authorize('update', $company);

        $integration = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', $pluginKey)
            ->firstOrFail();

        if ($integration->status !== ConnectedIntegrationStatus::Connected) {
            throw new ConnectedIntegrationReauthorizationRequired('The connected integration must be authorized again.');
        }

        if (filled($integration->access_token)
            && ($integration->expires_at === null || $integration->expires_at->isAfter(now()->addMinute()))) {
            return $integration->access_token;
        }

        return Cache::lock("connected-integration-refresh:{$integration->getKey()}", 30)
            ->block(5, function () use ($integration): string {
                $integration->refresh();

                if (filled($integration->access_token)
                    && ($integration->expires_at === null || $integration->expires_at->isAfter(now()->addMinute()))) {
                    return $integration->access_token;
                }

                if (blank($integration->refresh_token)) {
                    return $this->markReauthorizationRequired($integration);
                }

                $plugin = $this->registry->plugin($integration->plugin_key);

                try {
                    $token = $plugin->refreshAccessToken($integration->refresh_token, $plugin->redirectUri());
                } catch (OAuthRefreshTokenRejected $exception) {
                    return $this->markReauthorizationRequired($integration, $exception);
                } catch (Throwable $exception) {
                    $integration->forceFill(['last_error_at' => now()])->save();
                    throw $exception;
                }

                $integration->forceFill([
                    'status' => ConnectedIntegrationStatus::Connected,
                    'access_token' => $token->accessToken,
                    'refresh_token' => $token->refreshToken ?? $integration->refresh_token,
                    'granted_scopes' => $token->scopes === [] ? $integration->granted_scopes : $token->scopes,
                    'expires_at' => $token->expiresAt === null ? null : now()->setTimestamp($token->expiresAt),
                    'last_refreshed_at' => now(),
                    'last_error_at' => null,
                ])->save();

                return $token->accessToken;
            });
    }

    public function requireReauthorization(Company $company, User $user, string $pluginKey, ?Throwable $previous = null): never
    {
        Gate::forUser($user)->authorize('update', $company);

        $integration = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', $pluginKey)
            ->firstOrFail();

        $this->markReauthorizationRequired($integration, $previous);
    }

    private function markReauthorizationRequired(ConnectedIntegration $integration, ?Throwable $previous = null): never
    {
        $integration->forceFill([
            'status' => ConnectedIntegrationStatus::ReauthorizationRequired,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'last_error_at' => now(),
        ])->save();

        throw new ConnectedIntegrationReauthorizationRequired(
            'The connected integration must be authorized again.',
            previous: $previous,
        );
    }
}
