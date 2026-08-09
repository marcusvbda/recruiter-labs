<?php

namespace App\Services;

use App\Mail\Recruitment\RecruitmentMail;
use App\Models\CompanyEmailProviderSetting;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Mail\Markdown;
use LogicException;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Symfony\Component\Mime\Address;

class ResendRecruitmentEmailSender
{
    public function __construct(private readonly Markdown $markdown) {}

    public function send(
        CompanyEmailProviderSetting $providerSetting,
        RecruitmentMail $mailable,
        string $recipient,
        string $companyName,
        string $idempotencyKey,
    ): void {
        $apiKey = $providerSetting->api_key;
        $fromAddress = $providerSetting->validSenderAddress();
        $content = $mailable->content();
        $subject = $mailable->envelope()->subject;

        if (! is_string($apiKey) || $fromAddress === null) {
            throw new LogicException('The tenant email provider is not fully configured.');
        }

        if (! is_string($content->markdown) || blank($subject)) {
            throw new LogicException('Recruitment emails must define Markdown content and a subject.');
        }

        $viewData = array_merge($mailable->buildViewData(), $content->with);
        $client = $this->client($apiKey);

        $client->emails->send([
            'from' => (new Address($fromAddress, $companyName))->toString(),
            'to' => [$recipient],
            'subject' => $subject,
            'html' => (string) $this->markdown->render($content->markdown, $viewData),
            'text' => (string) $this->markdown->renderText($content->markdown, $viewData),
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function client(string $apiKey): Client
    {
        $key = ApiKey::from($apiKey);
        $baseUri = BaseUri::from('api.resend.com');
        $headers = Headers::withAuthorization($key);
        $guzzle = new GuzzleClient(['connect_timeout' => 5, 'timeout' => 30]);
        $transporter = new HttpTransporter($guzzle, $baseUri, $headers);

        return new Client($transporter);
    }
}
