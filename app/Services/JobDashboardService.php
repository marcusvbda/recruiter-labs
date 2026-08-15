<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobClickUtmParameter;
use App\Models\Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class JobDashboardService
{
    /**
     * @return array{
     *     clicks_count: int,
     *     applications_count: int,
     *     hired_count: int,
     *     running_days: int,
     *     remaining_days: int|null,
     *     has_ended: bool,
     *     status_distribution: list<array{name: string, color: string, count: int}>,
     *     utm_ranking: LengthAwarePaginator<int, array{name: string, value: string, clicks: int}>
     * }
     */
    public function get(Job $job): array
    {
        $job->loadCount(['clicks', 'applications']);

        $runningFrom = CarbonImmutable::parse($job->starts_at ?? $job->created_at)->startOfDay();
        $runningDays = $runningFrom->isFuture()
            ? 0
            : (int) $runningFrom->diffInDays(today());

        $endsAt = $job->ends_at === null
            ? null
            : CarbonImmutable::parse($job->ends_at);
        $hasEnded = $endsAt?->endOfDay()->isPast() ?? false;
        $remainingDays = $endsAt === null
            ? null
            : max(0, (int) today()->diffInDays($endsAt->startOfDay(), absolute: false));

        $statusDistribution = array_values(Status::query()
            ->where('company_id', $job->company_id)
            ->where('pipeline_id', $job->pipeline_id)
            ->withCount([
                'applications' => fn (Builder $query): Builder => $query->where('job_id', $job->getKey()),
            ])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (Status $status): array => [
                'name' => $status->name,
                'color' => preg_match('/^#[0-9a-f]{6}$/i', $status->color) ? $status->color : '#94a3b8',
                'count' => (int) $status->applications_count,
            ])
            ->values()
            ->all());

        $hiredCount = $job->applications()
            ->whereHas('status', fn (Builder $query): Builder => $query->where('is_hired', true))
            ->count();

        $utmRanking = JobClickUtmParameter::query()
            ->select(['name', 'value'])
            ->selectRaw('COUNT(*) as clicks')
            ->whereHas('jobClick', fn (Builder $query): Builder => $query->where('job_id', $job->getKey()))
            ->groupBy(['name', 'value'])
            ->orderByDesc('clicks')
            ->orderBy('name')
            ->orderBy('value')
            ->paginate(perPage: 10, pageName: 'utmPage')
            ->withQueryString()
            ->fragment('utm-ranking')
            ->through(fn (JobClickUtmParameter $parameter): array => [
                'name' => $parameter->name,
                'value' => $parameter->value,
                'clicks' => (int) $parameter->getAttribute('clicks'),
            ]);

        return [
            'clicks_count' => (int) $job->clicks_count,
            'applications_count' => (int) $job->applications_count,
            'hired_count' => $hiredCount,
            'running_days' => $runningDays,
            'remaining_days' => $remainingDays,
            'has_ended' => $hasEnded,
            'status_distribution' => $statusDistribution,
            'utm_ranking' => $utmRanking,
        ];
    }
}
