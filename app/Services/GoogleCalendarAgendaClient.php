<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

/**
 * Read-only view of a recruiter's own Google Calendar, used to render the
 * agenda page. Kept separate from {@see GoogleCalendarInterviewEventClient},
 * which owns the write path for interview events.
 */
class GoogleCalendarAgendaClient
{
    private const string PluginKey = 'google-calendar';

    private const string EventsUrl = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function __construct(
        private HttpFactory $http,
        private ConnectedIntegrationTokenManager $tokens,
    ) {}

    /**
     * List the recruiter's own events overlapping the given window.
     *
     * Times come back as RFC 3339 strings rather than date objects so the
     * result can be cached safely by any cache driver.
     *
     * @return list<array{id: string, summary: string|null, starts_at: string, ends_at: string, is_all_day: bool, html_link: string|null}>
     */
    public function listEvents(Company $company, User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $response = $this->http->withToken($this->tokens->accessToken($company, $user, self::PluginKey))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(3, 500, function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429);
            }, throw: true)
            ->get(self::EventsUrl, [
                'timeMin' => $from->toRfc3339String(),
                'timeMax' => $to->toRfc3339String(),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'showDeleted' => 'false',
                'maxResults' => 250,
            ])
            ->throw();

        $events = [];

        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json('items', []);

        foreach ($items as $item) {
            if (($item['status'] ?? null) === 'cancelled') {
                continue;
            }

            $event = $this->normalizeEvent($item);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: string, summary: string|null, starts_at: string, ends_at: string, is_all_day: bool, html_link: string|null}|null
     */
    private function normalizeEvent(array $item): ?array
    {
        $id = $item['id'] ?? null;

        if (! is_string($id) || blank($id)) {
            return null;
        }

        $startDateTime = data_get($item, 'start.dateTime');
        $endDateTime = data_get($item, 'end.dateTime');
        $startDate = data_get($item, 'start.date');
        $endDate = data_get($item, 'end.date');
        $isAllDay = ! is_string($startDateTime);

        if ($isAllDay && (! is_string($startDate) || ! is_string($endDate))) {
            return null;
        }

        if (! $isAllDay && ! is_string($endDateTime)) {
            return null;
        }

        $summary = $item['summary'] ?? null;
        $htmlLink = $item['htmlLink'] ?? null;

        return [
            'id' => $id,
            'summary' => is_string($summary) ? $summary : null,
            'starts_at' => CarbonImmutable::parse($isAllDay ? $startDate : $startDateTime)->toRfc3339String(),
            'ends_at' => CarbonImmutable::parse($isAllDay ? $endDate : $endDateTime)->toRfc3339String(),
            'is_all_day' => $isAllDay,
            'html_link' => is_string($htmlLink) ? $htmlLink : null,
        ];
    }
}
