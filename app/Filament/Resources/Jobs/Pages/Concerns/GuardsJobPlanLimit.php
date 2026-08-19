<?php

namespace App\Filament\Resources\Jobs\Pages\Concerns;

use App\Exceptions\PlanLimitExceededException;
use App\Filament\Clusters\Settings\Pages\PlanSettings;
use App\Models\Company;
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

    /**
     * Only job creation is guarded: the plan's allowance counts registered jobs,
     * so editing or publishing an existing one never consumes another slot.
     */
    protected function ensureJobCanBeCreated(): void
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        try {
            $this->limitManager->ensureCanCreateJob($company);
        } catch (PlanLimitExceededException) {
            self::notifyJobLimitReached();

            throw new Halt;
        }
    }

    public static function notifyJobLimitReached(): void
    {
        Notification::make()
            ->title(__('settings.plan.limit_reached'))
            ->body(__('settings.errors.job_limit_reached'))
            ->warning()
            ->actions([
                Action::make('managePlan')
                    ->label(__('settings.topbar.manage_plan'))
                    ->url(PlanSettings::getUrl())
                    ->button(),
            ])
            ->send();
    }
}
