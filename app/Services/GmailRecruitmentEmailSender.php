<?php

namespace App\Services;

use App\Contracts\RecruitmentEmailSender;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\RecruitmentEmailDeliveryStatus;
use App\Mail\Recruitment\RecruitmentMail;
use App\Models\CompanyEmailProviderSetting;
use App\Models\RecruitmentEmailDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class GmailRecruitmentEmailSender implements RecruitmentEmailSender
{
    public function __construct(
        private Markdown $markdown,
        private HttpFactory $http,
        private ConnectedIntegrationTokenManager $tokens,
    ) {}

    public function provider(): EmailProvider
    {
        return EmailProvider::Gmail;
    }

    public function isReady(CompanyEmailProviderSetting $providerSetting): bool
    {
        $integration = $providerSetting->connectedIntegration;

        return $providerSetting->provider === EmailProvider::Gmail
            && $providerSetting->credential_status === EmailCredentialStatus::Active
            && $integration !== null
            && $integration->company_id === $providerSetting->company_id
            && $integration->plugin_key === 'gmail'
            && $integration->status === ConnectedIntegrationStatus::Connected
            && $integration->account_email === $providerSetting->from_address
            && $integration->user->companies()->whereKey($providerSetting->company_id)->exists();
    }

    public function send(
        CompanyEmailProviderSetting $providerSetting,
        RecruitmentMail $mailable,
        string $recipient,
        string $companyName,
        string $idempotencyKey,
    ): void {
        $integration = $providerSetting->connectedIntegration;
        $company = $providerSetting->company;
        $fromAddress = $integration?->account_email;
        $content = $mailable->content();
        $subject = $mailable->envelope()->subject;

        if (! $this->isReady($providerSetting)
            || $integration === null
            || $company === null
            || ! is_string($fromAddress)
            || blank($subject)
            || ! is_string($content->markdown)) {
            throw new LogicException('The tenant Gmail provider is not fully configured.');
        }

        $accessToken = $this->tokens->accessToken($company, $integration->user, 'gmail');
        $viewData = array_merge($mailable->buildViewData(), $content->with);
        $email = (new Email)
            ->from(new Address($fromAddress, $companyName))
            ->to($recipient)
            ->subject($subject)
            ->html((string) $this->markdown->render($content->markdown, $viewData))
            ->text((string) $this->markdown->renderText($content->markdown, $viewData));
        $email->getHeaders()->addTextHeader('X-RecruiterLabs-Idempotency-Key', $idempotencyKey);
        $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');
        $delivery = $this->beginDeliveryAttempt($providerSetting, $idempotencyKey);

        if ($delivery === null) {
            return;
        }

        try {
            $response = $this->http->withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw])
                ->throw();
        } catch (ConnectionException $exception) {
            $delivery->update([
                'status' => RecruitmentEmailDeliveryStatus::Ambiguous,
                'last_exception_class' => $exception::class,
            ]);
            Log::warning('Gmail delivery outcome is ambiguous; automatic retries are suppressed.', [
                'company_id' => $company->getKey(),
                'provider_setting_id' => $providerSetting->getKey(),
                'idempotency_key' => $idempotencyKey,
                'exception_class' => $exception::class,
            ]);

            return;
        } catch (RequestException $exception) {
            $delivery->update([
                'status' => RecruitmentEmailDeliveryStatus::Pending,
                'last_exception_class' => $exception::class,
            ]);
            $errorItems = $exception->response->json('error.errors', []);
            $reasons = [];

            if (is_array($errorItems)) {
                foreach ($errorItems as $errorItem) {
                    if (is_array($errorItem) && is_string($errorItem['reason'] ?? null)) {
                        $reasons[] = $errorItem['reason'];
                    }
                }
            }

            if ($exception->response->status() === 401
                || array_intersect($reasons, ['authError', 'insufficientPermissions']) !== []) {
                $this->tokens->requireReauthorization($company, $integration->user, 'gmail', $exception);
            }

            throw $exception;
        }

        $messageId = $response->json('id');
        $delivery->update([
            'status' => RecruitmentEmailDeliveryStatus::Delivered,
            'provider_message_id' => is_string($messageId) ? $messageId : null,
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
                    'provider' => EmailProvider::Gmail,
                    'status' => RecruitmentEmailDeliveryStatus::Pending,
                ],
            );
            $delivery = RecruitmentEmailDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->sole();

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
}
