<?php

namespace App\Integrations\Google;

use App\Contracts\OAuthIntegrationPlugin;
use App\Data\OAuthTokenData;
use App\Exceptions\OAuthRefreshTokenRejected;
use App\Models\ConnectedIntegration;
use GuzzleHttp\Client;
use Illuminate\Http\Client\Factory as HttpFactory;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Token\AccessToken;
use LogicException;

abstract class GoogleOAuthPlugin implements OAuthIntegrationPlugin
{
    public function __construct(protected HttpFactory $http) {}

    public function redirectUri(): string
    {
        $configured = config('services.google.redirect_uri');

        return is_string($configured) && filled($configured) ? $configured : route('integrations.oauth.callback');
    }

    public function authorizationUrl(string $state, string $codeVerifier, string $redirectUri): string
    {
        return $this->provider($redirectUri)->getAuthorizationUrl([
            'state' => $state,
            'scope' => $this->scopes(),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): OAuthTokenData
    {
        $provider = $this->provider($redirectUri);
        $provider->setPkceCode($codeVerifier);
        $token = $provider->getAccessToken('authorization_code', ['code' => $code]);

        if (! $token instanceof AccessToken) {
            throw new LogicException('Google returned an unsupported access token implementation.');
        }
        /** @var GoogleUser $owner */
        $owner = $provider->getResourceOwner($token);

        return $this->tokenData(
            $token,
            (string) $owner->getId(),
            $owner->getEmail(),
            $owner->getName(),
            array_filter(['hosted_domain' => $owner->getHostedDomain()]),
        );
    }

    public function refreshAccessToken(string $refreshToken, string $redirectUri): OAuthTokenData
    {
        try {
            return $this->tokenData($this->provider($redirectUri)->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]));
        } catch (IdentityProviderException $exception) {
            $response = $exception->getResponseBody();

            if (is_array($response) && in_array($response['error'] ?? null, ['invalid_grant', 'invalid_token'], true)) {
                throw new OAuthRefreshTokenRejected('The provider rejected the refresh token.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function disconnect(ConnectedIntegration $integration): void {}

    /** @param list<string> $requiredScopes */
    protected function ensureScopes(OAuthTokenData $token, array $requiredScopes): void
    {
        foreach ($requiredScopes as $scope) {
            if (! in_array($scope, $token->scopes, true)) {
                throw new LogicException('A required Google OAuth scope was not granted.');
            }
        }
    }

    /** @return list<string> */
    abstract protected function scopes(): array;

    private function provider(string $redirectUri): Google
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (! is_string($clientId) || blank($clientId) || ! is_string($clientSecret) || blank($clientSecret)) {
            throw new LogicException('Google OAuth credentials are not configured.');
        }

        return new Google([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
        ], ['httpClient' => new Client(['connect_timeout' => 3, 'timeout' => 10])]);
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /** @param array<string, mixed> $metadata */
    private function tokenData(AccessTokenInterface $token, ?string $externalAccountId = null, ?string $accountEmail = null, ?string $accountName = null, array $metadata = []): OAuthTokenData
    {
        $scope = $token->getValues()['scope'] ?? null;

        return new OAuthTokenData(
            accessToken: (string) $token->getToken(),
            refreshToken: $token->getRefreshToken(),
            expiresAt: $token->getExpires(),
            scopes: is_string($scope) ? array_values(array_filter(explode(' ', $scope))) : [],
            externalAccountId: $externalAccountId,
            accountEmail: $accountEmail,
            accountName: $accountName,
            metadata: $metadata,
        );
    }
}
