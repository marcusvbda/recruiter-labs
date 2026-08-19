<?php

namespace App\Filament\Clusters\Settings\Concerns;

use App\Data\UsageMetricData;
use App\Enums\AiProvider;
use App\Enums\Limit;
use App\Enums\UsageWarningState;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Number;

/**
 * Shared presentation of plan allowances and usage, used by the plan and AI
 * settings pages so both describe the same numbers in the same words.
 */
trait PresentsPlanUsage
{
    public function getRecord(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    /** @return array<string, mixed> */
    protected function usageMetricViewData(UsageMetricData $metric): array
    {
        $statusLabel = match (true) {
            $metric->isOverLimit => __('settings.plan.over_limit'),
            $metric->isReached => __('settings.plan.limit_reached'),
            $metric->warningState !== UsageWarningState::Normal => __('settings.plan.approaching_limit'),
            default => __('settings.plan.within_limit'),
        };

        return [
            'key' => $metric->limit->value,
            'label' => __("settings.limits.{$metric->limit->value}"),
            'icon' => $this->limitIcon($metric->limit),
            'used' => Number::format($metric->used),
            'limit' => $this->formatLimit($metric->limitValue),
            'remaining' => $metric->remaining,
            'percentage' => $metric->percentage,
            'percentage_label' => $metric->isUnlimited
                ? __('settings.plan.unlimited')
                : Number::percentage($metric->percentage),
            'bar_percentage' => min(100, max(0, $metric->percentage)),
            'warning_state' => $metric->isOverLimit ? 'exceeded' : $metric->warningState->value,
            'status_label' => $statusLabel,
            'badge_color' => match (true) {
                $metric->isReached, $metric->isOverLimit => 'danger',
                $metric->warningState !== UsageWarningState::Normal => 'warning',
                default => 'success',
            },
            'cycle_label' => $metric->cycleStart && $metric->cycleEnd
                ? __('settings.plan.cycle', [
                    'start' => $metric->cycleStart->translatedFormat('d M'),
                    'end' => $metric->cycleEnd->translatedFormat('d M Y'),
                ])
                : __('settings.plan.no_cycle'),
        ];
    }

    protected function formatLimit(?int $limit): string
    {
        if ($limit === null) {
            return __('settings.plan.unlimited');
        }

        $formattedLimit = Number::format($limit);

        return is_string($formattedLimit) ? $formattedLimit : (string) $limit;
    }

    protected function providerLabel(AiProvider $provider): string
    {
        return $provider === AiProvider::Own
            ? __('settings.ai.own_key.name')
            : __('settings.ai.platform.name');
    }

    protected function limitIcon(Limit $limit): string
    {
        return match ($limit) {
            Limit::Users => 'heroicon-o-user-group',
            Limit::Jobs => 'heroicon-o-briefcase',
            Limit::Applications => 'heroicon-o-document-text',
            Limit::AiAnalyses => 'heroicon-o-sparkles',
        };
    }

    protected function planIcon(Plan $plan): string
    {
        return match ($plan->slug) {
            'business' => 'heroicon-o-building-office-2',
            'pro' => 'heroicon-o-rocket-launch',
            default => 'heroicon-o-paper-airplane',
        };
    }
}
