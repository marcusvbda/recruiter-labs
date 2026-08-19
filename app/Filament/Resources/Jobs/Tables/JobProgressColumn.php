<?php

namespace App\Filament\Resources\Jobs\Tables;

use App\Models\Job;
use App\Services\RecruitmentProgressService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The compact "how far has this process moved?" summary, shared by the jobs
 * list and the overview so both read identically.
 */
class JobProgressColumn
{
    public static function make(string $name = 'progress'): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('jobs.progress.label'))
            ->state(fn (Job $record): Htmlable => self::render($record))
            ->html()
            ->wrap();
    }

    private static function render(Job $record): Htmlable
    {
        $progressService = app(RecruitmentProgressService::class);

        return new HtmlString(
            view('filament.resources.jobs.components.progress-summary', [
                'progress' => $progressService->forJob($record),
                'isStalled' => $progressService->isStalled($record),
            ])->render(),
        );
    }
}
