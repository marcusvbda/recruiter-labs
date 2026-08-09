<?php

namespace App\Data;

final readonly class OAuthAuthorizationState
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $pluginKey,
        public string $codeVerifier,
        public string $returnUrl,
    ) {}
}
