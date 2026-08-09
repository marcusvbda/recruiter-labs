<?php

namespace App\Contracts;

use App\Data\OAuthTokenData;
use App\Models\ConnectedIntegration;

interface OAuthIntegrationPlugin
{
    public function key(): string;
    public function label(): string;
    public function description(): string;
    public function category(): string;
    public function icon(): string;
    /** @return list<string> */
    public function capabilities(): array;
    public function redirectUri(): string;
    public function authorizationUrl(string $state, string $codeVerifier, string $redirectUri): string;
    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): OAuthTokenData;
    public function refreshAccessToken(string $refreshToken, string $redirectUri): OAuthTokenData;
    public function validateConnection(OAuthTokenData $token): void;
    public function afterConnected(ConnectedIntegration $integration): void;
    public function disconnect(ConnectedIntegration $integration): void;
}
