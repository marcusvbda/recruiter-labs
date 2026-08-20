<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\User;
use App\Services\RecruitmentAttentionService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The recruiter's work queue, and the reason the overview exists.
 *
 * It sits above every metric on the page: a number tells a recruiter how things
 * are, this tells them what to do about it, and each row links straight to the
 * place the work happens. Items are derived by
 * {@see RecruitmentAttentionService} — nothing here is a stored task, and
 * nothing here acts on a candidate by itself.
 */
class RecruitmentAttentionWidget extends Widget
{
    protected string $view = 'filament.widgets.recruitment-attention';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        if (! $company instanceof Company || ! $recruiter instanceof User) {
            return ['items' => [], 'hidden_count' => 0];
        }

        $queue = app(RecruitmentAttentionService::class)->for($company, $recruiter);

        return [
            'items' => $queue->toArray(),
            'hidden_count' => $queue->hiddenCount(),
        ];
    }
}
