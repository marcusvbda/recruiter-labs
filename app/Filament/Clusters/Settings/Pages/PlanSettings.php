<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Actions\ChangeCompanyPlan;
use App\Data\UsageMetricData;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Filament\Clusters\Settings\Concerns\PresentsPlanUsage;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Plan;
use App\Services\CompanyUsageService;
use App\Services\PlanComparisonService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PlanSettings extends Page
{
    use PresentsPlanUsage;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.clusters.settings.pages.plan-page';

    /** @var array<string, mixed> */
    public array $planSettings = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.tabs.plan');
    }

    public function getTitle(): string
    {
        return __('settings.plan.title');
    }

    public function getSubheading(): string
    {
        return __('settings.plan.subtitle');
    }

    public function mount(CompanyUsageService $usageService, PlanComparisonService $comparisonService): void
    {
        $this->refreshPlanState($usageService, $comparisonService);
    }

    public function changePlanAction(): Action
    {
        return Action::make('changePlan')
            ->action(function (
                array $arguments,
                ChangeCompanyPlan $changeCompanyPlan,
                CompanyUsageService $usageService,
                PlanComparisonService $comparisonService,
            ): void {
                $plan = $this->resolvePlan($arguments);

                $changeCompanyPlan->run($this->getCompany(), $plan, $this->getRecord());
                $this->refreshPlanState($usageService, $comparisonService);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__('settings.notifications.plan_changed', ['plan' => $plan->name]))
                    ->success()
                    ->send();
            });
    }

    private function refreshPlanState(
        CompanyUsageService $usageService,
        PlanComparisonService $comparisonService,
    ): void {
        $company = $this->getCompany();
        $company->refresh()->load(['plan', 'aiSetting']);
        $usage = $usageService->summary($company);

        $this->planSettings = [
            'current_plan' => [
                'id' => $company->plan->getKey(),
                'name' => $company->plan->name,
                'slug' => $company->plan->slug,
            ],
            'plans' => Plan::query()
                ->orderBy('sort_order')
                ->get()
                ->map(function (Plan $plan) use ($company, $comparisonService): array {
                    $comparison = $comparisonService->compare($company, $plan);

                    return [
                        'id' => $plan->getKey(),
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'description' => __("settings.plans.{$plan->slug}"),
                        'icon' => $this->planIcon($plan),
                        'is_current' => $comparison->direction === 'current',
                        'direction' => $comparison->direction,
                        'limits' => array_map(
                            fn (Limit $limit): array => [
                                'key' => $limit->value,
                                'label' => __("settings.limits.{$limit->value}"),
                                'icon' => $this->limitIcon($limit),
                                'value' => $this->formatLimit($plan->getLimit($limit)),
                            ],
                            Limit::cases(),
                        ),
                        'features' => collect($plan->features ?? [])
                            ->map(fn (string $feature): string => Feature::from($feature)->label())
                            ->values()
                            ->all(),
                    ];
                })
                ->all(),
            'usage' => collect($usage->metrics)
                ->map(fn (UsageMetricData $metric): array => $this->usageMetricViewData($metric))
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function resolvePlan(array $arguments): Plan
    {
        $planId = $arguments['plan'] ?? null;

        abort_unless(is_numeric($planId), 404);

        return Plan::query()->findOrFail((int) $planId);
    }
}
