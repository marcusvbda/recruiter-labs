<?php

namespace App\Listeners;

use App\Events\ApplicationCreated;
use App\Data\ApplicationEmailContext;
use App\Enums\EmailNotificationType;
use App\Models\Application;
use App\Services\RecruitmentEmailDispatcher;

class DispatchApplicationReceivedEmail
{
    public function __construct(private readonly RecruitmentEmailDispatcher $dispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(ApplicationCreated $event): void
    {
        $application = Application::query()
            ->with(['candidate', 'job', 'company'])
            ->find($event->applicationId);

        if (! $application instanceof Application) {
            return;
        }

        $this->dispatcher->dispatch(
            $application->company,
            EmailNotificationType::ApplicationReceived,
            ApplicationEmailContext::fromApplication($application),
        );
    }
}
