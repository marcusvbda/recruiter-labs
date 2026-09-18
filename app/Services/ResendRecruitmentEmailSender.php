<?php

namespace App\Services;

use App\Contracts\RecruitmentEmailSender;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\RecruitmentEmailDeliveryStatus;
use App\Mail\Recruitment\RecruitmentMail;
use App\Models\CompanyEmailProviderSetting;
use App\Models\RecruitmentEmailDelivery;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Resend\Client;
use Resend\Exceptions\TransporterException;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Symfony\Component\Mime\Address;

class ResendRecruitmentEmailSender implements RecruitmentEmailSender
{
    public function __construct(private readonly Markdown $markdown) {}

    public function provider(): EmailProvider
    {
        return EmailProvider::Resend;
    }

    public function isReady(CompanyEmailProviderSetting $providerSetting): bool
    {
        return $providerSetting->provider === EmailProvider::Resend
            && $providerSetting->credential_status === EmailCredentialStatus::Active
            && filled($providerSetting->api_key)
            && $providerSetting->validSenderAddress() !== null;
    }

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

        $delivery = $this->beginDeliveryAttempt($providerSetting, $idempotencyKey);

        if ($delivery === null) {
            return;
        }

        try {
            $response = $client->emails->send([
                'from' => (new Address($fromAddress, $companyName))->toString(),
                'to' => [$recipient],
                'subject' => $subject,
                'html' => (string) $this->markdown->render($content->markdown, $viewData),
                'text' => (string) $this->markdown->renderText($content->markdown, $viewData),
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (TransporterException $exception) {
            $delivery->update([
                'status' => RecruitmentEmailDeliveryStatus::Ambiguous,
                'last_exception_class' => $exception::class,
            ]);
            Log::warning('Resend delivery outcome is ambiguous; automatic retries are suppressed.', [
                'company_id' => $providerSetting->company_id,
                'provider_setting_id' => $providerSetting->getKey(),
                'idempotency_key' => $idempotencyKey,
                'exception_class' => $exception::class,
            ]);

            return;
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => RecruitmentEmailDeliveryStatus::Pending,
                'last_exception_class' => $exception::class,
            ]);

            throw $exception;
        }

        $delivery->update([
            'status' => RecruitmentEmailDeliveryStatus::Delivered,
            'provider_message_id' => is_string($response->id ?? null) ? $response->id : null,
            'last_exception_class' => null,
            'delivered_at' => now(),
        ]);
    }

    private function beginDeliveryAttempt(
        CompanyEmailProviderSetting $providerSetting,
        string $idempotencyKey,
    ): ?RecruitmentEmailDelivery {
        return DB::transaction(function () use ($providerSetting, $idempotencyKey): ?RecruitmentEmailDelivery {
            $delivery = RecruitmentEmailDelivery::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'company_id' => $providerSetting->company_id,
                    'provider_setting_id' => $providerSetting->getKey(),
                    'provider' => EmailProvider::Resend,
                    'status' => RecruitmentEmailDeliveryStatus::Pending,
                ],
            );
            $delivery = RecruitmentEmailDelivery::query()->whereKey($delivery->getKey())->lockForUpdate()->sole();

            if (in_array($delivery->status, [RecruitmentEmailDeliveryStatus::Delivered, RecruitmentEmailDeliveryStatus::Ambiguous], true)) {
                return null;
            }

            if ($delivery->status === RecruitmentEmailDeliveryStatus::Sending) {
                $delivery->update([
                    'status' => RecruitmentEmailDeliveryStatus::Ambiguous,
                    'last_exception_class' => 'InterruptedDeliveryAttempt',
                ]);

                return null;
            }

            $delivery->update([
                'status' => RecruitmentEmailDeliveryStatus::Sending,
                'attempts' => $delivery->attempts + 1,
                'last_exception_class' => null,
                'last_attempted_at' => now(),
            ]);

            return $delivery;
        });
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
