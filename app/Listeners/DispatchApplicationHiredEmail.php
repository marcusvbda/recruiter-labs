<?php

namespace App\Listeners;

use App\Data\ApplicationEmailContext;
use App\Enums\EmailNotificationType;
use App\Events\ApplicationHired;
use App\Models\Application;
use App\Services\RecruitmentEmailDispatcher;

class DispatchApplicationHiredEmail
{
    public function __construct(private readonly RecruitmentEmailDispatcher $dispatcher) {}

    /**
     * Handle the event.
     */
    public function handle(ApplicationHired $event): void
    {
        $application = Application::query()
            ->with(['candidate', 'job', 'company'])
            ->find($event->applicationId);

        if (! $application instanceof Application) {
            return;
        }

        $this->dispatcher->dispatch(
            $application->company,
            EmailNotificationType::ApplicationHired,
            ApplicationEmailContext::fromApplication($application),
        );
    }
}
