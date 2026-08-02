<?php

namespace App\Services;

use App\Data\AiCredentialTestResultData;
use App\Enums\AiCredentialStatus;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiCredentialTester
{
    public function test(string $apiKey, string $model): AiCredentialTestResultData
    {
        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.openai.connect_timeout', 3))
                ->timeout((int) config('services.openai.timeout', 8))
                ->get('/v1/models/'.rawurlencode($model));

            if ($response->successful()) {
                return new AiCredentialTestResultData(
                    success: true,
                    status: AiCredentialStatus::Active,
                    messageKey: 'settings.ai.messages.connection_succeeded',
                );
            }
        } catch (Throwable) {
            // Fail closed without reporting an exception that could contain request credentials.
        }

        return new AiCredentialTestResultData(
            success: false,
            status: AiCredentialStatus::Invalid,
            messageKey: 'settings.ai.messages.connection_failed',
        );
    }
}
