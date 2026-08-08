<?php

namespace App\Services;

use App\Data\EmailCredentialTestResultData;
use App\Enums\EmailCredentialStatus;
use GuzzleHttp\Client as GuzzleClient;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Throwable;

class ResendCredentialTester
{
    public function test(string $apiKey): EmailCredentialTestResultData
    {
        try {
            $key = ApiKey::from($apiKey);
            $baseUri = BaseUri::from('api.resend.com');
            $headers = Headers::withAuthorization($key);
            $guzzle = new GuzzleClient(['connect_timeout' => 3, 'timeout' => 8]);
            $transporter = new HttpTransporter($guzzle, $baseUri, $headers);
            $client = new Client($transporter);

            $client->domains->list();

            return new EmailCredentialTestResultData(
                success: true,
                status: EmailCredentialStatus::Active,
                messageKey: 'settings.email.messages.connection_succeeded',
            );
        } catch (Throwable) {
            // Fail closed without reporting an exception that could contain request credentials.
        }

        return new EmailCredentialTestResultData(
            success: false,
            status: EmailCredentialStatus::Invalid,
            messageKey: 'settings.email.messages.connection_failed',
        );
    }
}
