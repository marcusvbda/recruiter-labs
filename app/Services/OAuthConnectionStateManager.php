<?php

namespace App\Services;

use App\Data\OAuthAuthorizationState;
use App\Exceptions\InvalidOAuthState;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;
use Throwable;

class OAuthConnectionStateManager
{
    public function __construct(
        private CacheFactory $cache,
        private Encrypter $encrypter,
    ) {}

    /** @return array{state: string, code_verifier: string} */
    public function issue(Company $company, User $user, string $pluginKey, string $returnUrl): array
    {
        $nonce = Str::random(64);
        $codeVerifier = Str::random(96);
        $expiresAt = now()->addSeconds((int) config('connected-integrations.state_ttl', 600));

        $payload = json_encode([
            'nonce' => $nonce,
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'plugin_key' => $pluginKey,
            'code_verifier' => $codeVerifier,
            'return_url' => $returnUrl,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $this->cache->store()->put($this->cacheKey($nonce), true, $expiresAt);

        return [
            'state' => $this->encrypter->encryptString($payload),
            'code_verifier' => $codeVerifier,
        ];
    }

    public function consume(string $encryptedState, int $expectedUserId): OAuthAuthorizationState
    {
        try {
            $payload = json_decode($this->encrypter->decryptString($encryptedState), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidOAuthState('The OAuth state is invalid.', previous: $exception);
        }

        if (! is_array($payload)
            || ! is_string($payload['nonce'] ?? null)
            || ! is_int($payload['company_id'] ?? null)
            || ! is_int($payload['user_id'] ?? null)
            || ! is_string($payload['plugin_key'] ?? null)
            || ! is_string($payload['code_verifier'] ?? null)
            || ! is_string($payload['return_url'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] < now()->getTimestamp()) {
            throw new InvalidOAuthState('The OAuth state is expired or has already been used.');
        }

        if ($payload['user_id'] !== $expectedUserId) {
            throw new InvalidOAuthState('The OAuth state belongs to another user.');
        }

        try {
            $cacheKey = $this->cacheKey($payload['nonce']);
            $repository = $this->cache->store();
            $store = $repository->getStore();

            if (! $store instanceof LockProvider) {
                throw new InvalidOAuthState('The configured cache store cannot safely consume OAuth state.');
            }

            $wasUnused = $store->lock("{$cacheKey}:consume", 10)
                ->block(3, fn (): bool => $repository->pull($cacheKey) === true);
        } catch (Throwable $exception) {
            throw new InvalidOAuthState('The OAuth state could not be consumed safely.', previous: $exception);
        }

        if (! $wasUnused) {
            throw new InvalidOAuthState('The OAuth state is expired or has already been used.');
        }

        return new OAuthAuthorizationState(
            companyId: $payload['company_id'],
            userId: $payload['user_id'],
            pluginKey: $payload['plugin_key'],
            codeVerifier: $payload['code_verifier'],
            returnUrl: $payload['return_url'],
        );
    }

    private function cacheKey(string $nonce): string
    {
        return 'oauth-connection-state:'.hash('sha256', $nonce);
    }
}
