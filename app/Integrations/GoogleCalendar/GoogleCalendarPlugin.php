<?php

namespace App\Integrations\GoogleCalendar;

use App\Data\OAuthTokenData;
use App\Integrations\Google\GoogleOAuthPlugin;
use App\Models\ConnectedIntegration;

class GoogleCalendarPlugin extends GoogleOAuthPlugin
{
    public function key(): string { return 'google-calendar'; }
    public function label(): string { return __('connected_integrations.plugins.google_calendar.label'); }
    public function description(): string { return __('connected_integrations.plugins.google_calendar.description'); }
    public function category(): string { return __('connected_integrations.plugins.google_calendar.category'); }
    public function icon(): string { return 'heroicon-o-calendar-days'; }
    public function capabilities(): array { return ['availability.read', 'events.create', 'events.update', 'events.delete']; }

    public function validateConnection(OAuthTokenData $token): void
    {
        $this->ensureScopes($token, [
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.events.freebusy',
        ]);

        $this->http->withToken($token->accessToken)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'maxResults' => 1,
                'singleEvents' => 'true',
            ])
            ->throw();
    }

    public function afterConnected(ConnectedIntegration $integration): void {}

    protected function scopes(): array
    {
        /** @var list<string> $scopes */
        $scopes = config('connected-integrations.google.scopes', []);

        return $scopes;
    }
}
