<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Gives a company the recruitment workflow it needs to post its first job.
 * Idempotent: a company that already has a default pipeline keeps it.
 */
class ProvisionDefaultPipeline
{
    /**
     * The workflow every new pipeline starts from, here and in the Filament
     * pipeline creator. Recruiters edit it down from there.
     *
     * @var list<array{name: string, color: string, is_hired: bool}>
     */
    public const array STARTER_STATUSES = [
        ['name' => 'Applied', 'color' => '#3b82f6', 'is_hired' => false],
        ['name' => 'Screening', 'color' => '#f59e0b', 'is_hired' => false],
        ['name' => 'Interview', 'color' => '#8b5cf6', 'is_hired' => false],
        ['name' => 'Offer', 'color' => '#06b6d4', 'is_hired' => false],
        ['name' => 'Hired', 'color' => '#22c55e', 'is_hired' => true],
        ['name' => 'Rejected', 'color' => '#ef4444', 'is_hired' => false],
    ];

    public function handle(Company $company): Pipeline
    {
        $existing = $company->pipelines()->where('is_default', true)->first();

        if ($existing instanceof Pipeline) {
            return $existing;
        }

        return DB::transaction(function () use ($company): Pipeline {
            $pipeline = $company->pipelines()->firstOrCreate(
                ['name' => __('pipelines.default.name')],
                [
                    'description' => __('pipelines.default.description'),
                    'is_default' => true,
                ],
            );

            if (! $pipeline->is_default) {
                $pipeline->update(['is_default' => true]);
            }

            $this->seedStarterStatuses($pipeline);

            return $pipeline;
        });
    }

    public function seedStarterStatuses(Pipeline $pipeline): void
    {
        if ($pipeline->statuses()->exists()) {
            return;
        }

        foreach (self::STARTER_STATUSES as $order => $status) {
            $pipeline->statuses()->create([
                'company_id' => $pipeline->company_id,
                'name' => $status['name'],
                'color' => $status['color'],
                'order' => $order + 1,
                'is_hired' => $status['is_hired'],
            ]);
        }
    }
}
