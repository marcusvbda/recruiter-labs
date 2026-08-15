<?php

namespace App\Actions;

use App\Models\Pipeline;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

/**
 * Copies a pipeline's configuration — its statuses, their order, colors and
 * on-enter communication. Never its jobs, applications or any other operational
 * history, and never the default flag.
 */
class DuplicatePipeline
{
    public function handle(Pipeline $pipeline): Pipeline
    {
        return DB::transaction(function () use ($pipeline): Pipeline {
            $copy = Pipeline::query()->create([
                'company_id' => $pipeline->company_id,
                'name' => $this->availableName($pipeline),
                'description' => $pipeline->description,
                'is_default' => false,
            ]);

            $pipeline->statuses()->each(function (Status $status) use ($copy): void {
                $copy->statuses()->create([
                    'company_id' => $copy->company_id,
                    'name' => $status->name,
                    'color' => $status->color,
                    'order' => $status->order,
                    'is_hired' => $status->is_hired,
                    'sends_email' => $status->sends_email,
                    'email_subject' => $status->email_subject,
                    'email_body' => $status->email_body,
                ]);
            });

            return $copy;
        });
    }

    /**
     * "External Recruitment" becomes "External Recruitment - Copy", then
     * "… - Copy 2" and so on, since pipeline names are unique per company.
     */
    private function availableName(Pipeline $pipeline): string
    {
        $base = __('pipelines.duplicate.name', ['name' => $pipeline->name]);
        $name = $base;
        $suffix = 1;

        while ($this->nameTaken($pipeline, $name)) {
            $suffix++;
            $name = "{$base} {$suffix}";
        }

        return $name;
    }

    private function nameTaken(Pipeline $pipeline, string $name): bool
    {
        return Pipeline::query()
            ->where('company_id', $pipeline->company_id)
            ->where('name', $name)
            ->exists();
    }
}
