<?php

namespace App\Http\Controllers;

use App\Actions\CompleteConnectedIntegration;
use App\Actions\DisconnectConnectedIntegration;
use App\Enums\ConnectedIntegrationStatus;
use App\Exceptions\InvalidOAuthState;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use App\Services\OAuthConnectionStateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConnectedIntegrationOAuthController extends Controller
{
    public function __construct(
        private ConnectedIntegrationRegistry $registry,
        private OAuthConnectionStateManager $states,
        private CompleteConnectedIntegration $completeConnection,
        private DisconnectConnectedIntegration $disconnectIntegration,
    ) {}

    public function connect(Request $request, Company $company, string $plugin): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('update', $company);

        $existing = $this->integration($company, $user, $plugin);
        abort_if($existing?->status !== ConnectedIntegrationStatus::Revoked && $existing !== null, 404);

        return $this->startAuthorization($request, $company, $user, $plugin);
    }

    public function reconnect(Request $request, Company $company, string $plugin): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('update', $company);

        $existing = $this->integration($company, $user, $plugin);
        abort_if($existing === null || $existing->status === ConnectedIntegrationStatus::Revoked, 404);

        return $this->startAuthorization($request, $company, $user, $plugin);
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $state = $this->states->consume((string) $request->query('state'), (int) $user->getKey());
        } catch (InvalidOAuthState $exception) {
            return redirect('/admin')->with([
                'connected_integration_status' => 'error',
                'connected_integration_message' => $exception->getMessage(),
            ]);
        }

        $company = Company::query()->findOrFail($state->companyId);
        Gate::forUser($user)->authorize('update', $company);
        $plugin = $state->pluginKey;
        $oauthPlugin = $this->registry->plugin($plugin);

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->to($state->returnUrl)->with([
                'connected_integration_status' => 'error',
                'connected_integration_message' => __('connected_integrations.notifications.cancelled', ['plugin' => $oauthPlugin->label()]),
            ]);
        }

        try {
            $token = $oauthPlugin->exchangeAuthorizationCode(
                (string) $request->query('code'),
                $state->codeVerifier,
                $oauthPlugin->redirectUri(),
            );
            $oauthPlugin->validateConnection($token);
            $integration = $this->completeConnection->run($company, $user, $plugin, $token);
            $oauthPlugin->afterConnected($integration);
        } catch (Throwable $exception) {
            Log::warning('Connected integration OAuth callback failed.', [
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'plugin_key' => $plugin,
                'exception_class' => $exception::class,
            ]);

            return redirect()->to($state->returnUrl)->with([
                'connected_integration_status' => 'error',
                'connected_integration_message' => __('connected_integrations.notifications.connect_failed', ['plugin' => $oauthPlugin->label()]),
            ]);
        }

        return redirect()->to($state->returnUrl)->with([
            'connected_integration_status' => 'connected',
            'connected_integration_message' => __('connected_integrations.notifications.connected', ['plugin' => $oauthPlugin->label()]),
        ]);
    }

    public function disconnect(Request $request, Company $company, string $plugin): RedirectResponse
    {
        $pluginLabel = $this->registry->plugin($plugin)->label();
        $this->disconnectIntegration->run($company, $this->user($request), $plugin);

        return back()->with([
            'connected_integration_status' => 'disconnected',
            'connected_integration_message' => __('connected_integrations.notifications.disconnected', ['plugin' => $pluginLabel]),
        ]);
    }

    private function startAuthorization(Request $request, Company $company, User $user, string $plugin): RedirectResponse
    {
        $state = $this->states->issue($company, $user, $plugin, $this->returnUrl($request, $company));
        $oauthPlugin = $this->registry->plugin($plugin);
        $authorizationUrl = $oauthPlugin->authorizationUrl(
            $state['state'],
            $state['code_verifier'],
            $oauthPlugin->redirectUri(),
        );

        return redirect()->away($authorizationUrl);
    }

    private function integration(Company $company, User $user, string $plugin): ?ConnectedIntegration
    {
        return ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', $plugin)
            ->first();
    }

    private function returnUrl(Request $request, Company $company): string
    {
        $fallback = url("/admin/{$company->slug}/integrations");
        $referer = $request->headers->get('referer');

        if (! is_string($referer)) {
            return $fallback;
        }

        $refererParts = parse_url($referer);
        $appParts = parse_url(config('app.url'));
        $tenantPathPrefix = "/admin/{$company->slug}/";

        if (($refererParts['scheme'] ?? null) !== ($appParts['scheme'] ?? null)
            || ($refererParts['host'] ?? null) !== ($appParts['host'] ?? null)
            || ($refererParts['port'] ?? null) !== ($appParts['port'] ?? null)
            || ! str_starts_with($refererParts['path'] ?? '', $tenantPathPrefix)) {
            return $fallback;
        }

        return $referer;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
