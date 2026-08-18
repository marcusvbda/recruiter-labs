<?php

namespace App\Services;

use App\Data\GoogleCalendarInterviewEventData;
use App\Enums\InterviewRsvpStatus;
use App\Models\Company;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class GoogleCalendarInterviewEventClient
{
    private const string PluginKey = 'google-calendar';

    private const string EventsUrl = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function __construct(
        private HttpFactory $http,
        private ConnectedIntegrationTokenManager $tokens,
    ) {}

    public function create(Company $company, User $user, Interview $interview): GoogleCalendarInterviewEventData
    {
        try {
            $response = $this->request($company, $user)
                ->withQueryParameters([
                    'conferenceDataVersion' => 1,
                    'sendUpdates' => 'all',
                ])
                ->post(self::EventsUrl, $this->eventPayload($interview))
                ->throw();
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 409) {
                $this->handleAuthorizationFailure($company, $user, $exception);

                throw $exception;
            }

            return $this->find($company, $user, $interview->calendar_event_id, $interview);
        }

        return $this->eventData($response, $interview);
    }

    public function newEventId(): string
    {
        return 'rl'.bin2hex(random_bytes(16));
    }

    public function update(Company $company, User $user, Interview $interview): GoogleCalendarInterviewEventData
    {
        try {
            $response = $this->request($company, $user)
                ->withQueryParameters([
                    'conferenceDataVersion' => 1,
                    'sendUpdates' => 'all',
                ])
                ->patch($this->eventUrl($interview->calendar_event_id), $this->eventPayload($interview, false))
                ->throw();
        } catch (RequestException $exception) {
            $this->handleAuthorizationFailure($company, $user, $exception);

            throw $exception;
        }

        return $this->eventData($response, $interview);
    }

    public function delete(Company $company, User $user, string $eventId): void
    {
        try {
            $this->request($company, $user)
                ->withQueryParameters(['sendUpdates' => 'all'])
                ->delete($this->eventUrl($eventId))
                ->throw();
        } catch (RequestException $exception) {
            if (in_array($exception->response->status(), [404, 410], true)) {
                return;
            }

            $this->handleAuthorizationFailure($company, $user, $exception);

            throw $exception;
        }
    }

    public function find(
        Company $company,
        User $user,
        string $eventId,
        ?Interview $interview = null,
    ): GoogleCalendarInterviewEventData {
        try {
            $response = $this->request($company, $user)
                ->get($this->eventUrl($eventId))
                ->throw();
        } catch (RequestException $exception) {
            $this->handleAuthorizationFailure($company, $user, $exception);

            throw $exception;
        }

        return $this->eventData($response, $interview);
    }

    private function request(Company $company, User $user): PendingRequest
    {
        return $this->http->withToken($this->tokens->accessToken($company, $user, self::PluginKey))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(4, function (int $attempt, \Throwable $exception): int {
                return min(8000, 1000 * (2 ** ($attempt - 1))) + random_int(0, 500);
            }, function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if (! $exception instanceof RequestException) {
                    return false;
                }

                $response = $exception->response;
                $reasons = $response->collect('error.errors')
                    ->pluck('reason')
                    ->filter(fn (mixed $reason): bool => is_string($reason))
                    ->all();

                return $response->serverError()
                    || $response->status() === 429
                    || ($response->status() === 403
                        && array_intersect($reasons, ['rateLimitExceeded', 'userRateLimitExceeded']) !== []);
            });
    }

    /** @return array<string, mixed> */
    private function eventPayload(Interview $interview, bool $creating = true): array
    {
        $interview->loadMissing('application.job', 'application.candidate');
        $application = $interview->application;
        $candidate = $application->candidate;
        $job = $application->job;

        $payload = [
            'summary' => trans('calendar.event.summary', ['job' => $job->name], $job->application_locale->value),
            'description' => trans('calendar.event.description', [
                'candidate' => $candidate->name,
                'job' => $job->name,
            ], $job->application_locale->value),
            'start' => [
                'dateTime' => $interview->scheduled_at->setTimezone($interview->timezone)->toRfc3339String(),
                'timeZone' => $interview->timezone,
            ],
            'end' => [
                'dateTime' => $interview->ends_at->setTimezone($interview->timezone)->toRfc3339String(),
                'timeZone' => $interview->timezone,
            ],
            'attendees' => [[
                'email' => $candidate->email,
            ]],
        ];

        if ($creating) {
            $payload['id'] = $interview->calendar_event_id;
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => $this->conferenceRequestId($interview),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $payload;
    }

    private function eventUrl(string $eventId): string
    {
        return self::EventsUrl.'/'.rawurlencode($eventId);
    }

    private function conferenceRequestId(Interview $interview): string
    {
        return 'recruiter-labs-'.$interview->calendar_event_id;
    }

    private function eventData(Response $response, ?Interview $interview = null): GoogleCalendarInterviewEventData
    {
        /** @var array<string, mixed> $event */
        $event = $response->json();
        $eventId = $event['id'] ?? $interview?->calendar_event_id;

        if (! is_string($eventId) || blank($eventId)) {
            throw new \LogicException('Google Calendar did not return an event ID.');
        }

        $meetingUrl = $event['hangoutLink'] ?? null;
        $entryPoints = data_get($event, 'conferenceData.entryPoints', []);

        if ((! is_string($meetingUrl) || blank($meetingUrl)) && is_array($entryPoints)) {
            foreach ($entryPoints as $entryPoint) {
                if (is_array($entryPoint)
                    && ($entryPoint['entryPointType'] ?? null) === 'video'
                    && is_string($entryPoint['uri'] ?? null)) {
                    $meetingUrl = $entryPoint['uri'];
                    break;
                }
            }
        }

        $candidateEmail = $interview?->application?->candidate?->email;
        $rsvpStatus = $this->rsvpStatus($event, $candidateEmail);
        $conferenceId = data_get($event, 'conferenceData.conferenceId');

        return new GoogleCalendarInterviewEventData(
            eventId: $eventId,
            conferenceId: is_string($conferenceId) ? $conferenceId : null,
            meetingUrl: is_string($meetingUrl) ? $meetingUrl : null,
            rsvpStatus: $rsvpStatus,
            isCancelled: ($event['status'] ?? null) === 'cancelled',
            conferenceCreationFailed: data_get($event, 'conferenceData.createRequest.status.statusCode') === 'failure',
        );
    }

    /** @param array<string, mixed> $event */
    private function rsvpStatus(array $event, ?string $candidateEmail): InterviewRsvpStatus
    {
        $attendees = $event['attendees'] ?? [];

        if (! is_array($attendees) || ! is_string($candidateEmail)) {
            return InterviewRsvpStatus::NeedsAction;
        }

        foreach ($attendees as $attendee) {
            if (! is_array($attendee)
                || ! is_string($attendee['email'] ?? null)
                || ! hash_equals(Str::lower($candidateEmail), Str::lower($attendee['email']))) {
                continue;
            }

            return match ($attendee['responseStatus'] ?? null) {
                'accepted' => InterviewRsvpStatus::Accepted,
                'declined' => InterviewRsvpStatus::Declined,
                'tentative' => InterviewRsvpStatus::Tentative,
                default => InterviewRsvpStatus::NeedsAction,
            };
        }

        return InterviewRsvpStatus::NeedsAction;
    }

    private function handleAuthorizationFailure(Company $company, User $user, RequestException $exception): void
    {
        $reasons = $exception->response->collect('error.errors')
            ->pluck('reason')
            ->filter(fn (mixed $reason): bool => is_string($reason))
            ->all();

        if ($exception->response->status() === 401
            || array_intersect($reasons, ['authError', 'insufficientPermissions']) !== []) {
            $this->tokens->requireReauthorization($company, $user, self::PluginKey, $exception);
        }
    }
}
