<?php

namespace App\Filament\Resources\Jobs\Pages\Concerns;

use App\Exceptions\PlanLimitExceededException;
use App\Filament\Pages\Settings;
use App\Models\Company;
use App\Models\Job;
use App\Services\LimitManager;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

trait GuardsJobPlanLimit
{
    private LimitManager $limitManager;

    public function boot(LimitManager $limitManager): void
    {
        $this->limitManager = $limitManager;
    }

    /** @param array<string, mixed> $attributes */
    protected function ensureJobCanBeSaved(array $attributes, ?Job $job = null): void
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        try {
            if ($job === null) {
                $this->limitManager->ensureCanCreateJob($company);
            } else {
                $this->limitManager->ensureCanSaveJob($company, $attributes, $job);
            }
        } catch (PlanLimitExceededException) {
            Notification::make()
                ->title(__('settings.plan.limit_reached'))
                ->body(__('settings.errors.job_limit_reached'))
                ->warning()
                ->actions([
                    Action::make('managePlan')
                        ->label(__('settings.topbar.manage_plan'))
                        ->url(Settings::getUrl(['section' => 'plan']))
                        ->button(),
                ])
                ->send();

            throw new Halt;
        }
    }
}
