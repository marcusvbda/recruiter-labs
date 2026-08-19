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
     * Each stage declares its role explicitly — which one is close to a decision
     * (`is_final_stage`) and which ones end the process (`is_terminal`) — because
     * nothing may infer that from a stage's name at runtime.
     *
     * @var list<array{name: string, color: string, is_final_stage: bool, is_hired: bool, is_terminal: bool}>
     */
    public const array STARTER_STATUSES = [
        ['name' => 'Applied', 'color' => '#3b82f6', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => false],
        ['name' => 'Screening', 'color' => '#f59e0b', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => false],
        ['name' => 'Interview', 'color' => '#8b5cf6', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => false],
        ['name' => 'Offer', 'color' => '#06b6d4', 'is_final_stage' => true, 'is_hired' => false, 'is_terminal' => false],
        ['name' => 'Hired', 'color' => '#22c55e', 'is_final_stage' => false, 'is_hired' => true, 'is_terminal' => true],
        ['name' => 'Rejected', 'color' => '#ef4444', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => true],
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
                'is_final_stage' => $status['is_final_stage'],
                'is_hired' => $status['is_hired'],
                'is_terminal' => $status['is_terminal'],
            ]);
        }
    }
}
