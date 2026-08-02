<?php

namespace App\Data;

use App\Enums\AiProvider;
use JsonSerializable;

class AiProviderConfigurationData implements JsonSerializable
{
    public function __construct(
        public readonly AiProvider $provider,
        private readonly ?string $apiKey,
        public readonly string $model,
        public readonly bool $usesOwnKey,
        public readonly bool $isConfigured,
    ) {}

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    /** @return array{provider: string, model: string, uses_own_key: bool, is_configured: bool} */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'model' => $this->model,
            'uses_own_key' => $this->usesOwnKey,
            'is_configured' => $this->isConfigured,
        ];
    }

    /** @return array{provider: string, model: string, uses_own_key: bool, is_configured: bool} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
