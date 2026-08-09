<?php

namespace App\Data;

final readonly class OAuthTokenData
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?int $expiresAt,
        public array $scopes = [],
        public ?string $externalAccountId = null,
        public ?string $accountEmail = null,
        public ?string $accountName = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
