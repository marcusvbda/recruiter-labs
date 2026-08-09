<?php

namespace App\Integrations\Gmail;

use App\Data\OAuthTokenData;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Integrations\Google\GoogleOAuthPlugin;
use App\Models\CompanyEmailProviderSetting;
use App\Models\ConnectedIntegration;
use Illuminate\Support\Facades\DB;
use LogicException;

class GmailPlugin extends GoogleOAuthPlugin
{
    public function key(): string
    {
        return 'gmail';
    }

    public function label(): string
    {
        return __('connected_integrations.plugins.gmail.label');
    }

    public function description(): string
    {
        return __('connected_integrations.plugins.gmail.description');
    }

    public function category(): string
    {
        return __('connected_integrations.plugins.gmail.category');
    }

    public function icon(): string
    {
        return asset('assets/image/icons/gmail.png');
    }

    public function capabilities(): array
    {
        return ['email.send'];
    }

    public function validateConnection(OAuthTokenData $token): void
    {
        $this->ensureScopes($token, ['https://www.googleapis.com/auth/gmail.send']);

        if (blank($token->externalAccountId) || filter_var($token->accountEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('The connected Gmail identity is incomplete.');
        }
    }

    public function afterConnected(ConnectedIntegration $integration): void
    {
        DB::transaction(function () use ($integration): void {
            $setting = CompanyEmailProviderSetting::query()->firstOrNew([
                'company_id' => $integration->company_id,
                'provider' => EmailProvider::Gmail->value,
            ]);

            if ($setting->exists && $setting->connected_integration_id !== null) {
                $linkedUserId = ConnectedIntegration::query()->whereKey($setting->connected_integration_id)->value('user_id');

                if ((int) $linkedUserId !== $integration->user_id) {
                    return;
                }
            }

            $setting->fill([
                'connected_integration_id' => $integration->getKey(),
                'from_address' => $integration->account_email,
                'credential_status' => EmailCredentialStatus::Active,
                'validated_at' => now(),
                'is_default' => $setting->exists && $setting->is_default,
            ])->save();
        });
    }

    protected function scopes(): array
    {
        /** @var list<string> $scopes */
        $scopes = config('connected-integrations.gmail.scopes', []);

        return $scopes;
    }
}
