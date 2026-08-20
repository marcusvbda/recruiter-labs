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
     * `attention_after_days` is a deliberately conservative starting point, not a
     * recruiting rule: it only exists so a new workspace sees the mechanism work,
     * and every value is editable per stage. Stages where waiting is normal —
     * interviews being scheduled around a candidate's availability — declare no
     * expectation at all.
     *
     * @var list<array{name: string, color: string, is_final_stage: bool, is_hired: bool, is_terminal: bool, attention_after_days: int|null}>
     */
    public const array STARTER_STATUSES = [
        ['name' => 'Applied', 'color' => '#3b82f6', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => false, 'attention_after_days' => 3],
        ['name' => 'Screening', 'color' => '#f59e0b', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => false, 'attention_after_days' => 5],
        ['name' => 'Interview', 'color' => '#8b5cf6', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => false, 'attention_after_days' => null],
        ['name' => 'Offer', 'color' => '#06b6d4', 'is_final_stage' => true, 'is_hired' => false, 'is_terminal' => false, 'attention_after_days' => 5],
        ['name' => 'Hired', 'color' => '#22c55e', 'is_final_stage' => false, 'is_hired' => true, 'is_terminal' => true, 'attention_after_days' => null],
        ['name' => 'Rejected', 'color' => '#ef4444', 'is_final_stage' => false, 'is_hired' => false, 'is_terminal' => true, 'attention_after_days' => null],
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
                'attention_after_days' => $status['attention_after_days'],
            ]);
        }
    }
}
