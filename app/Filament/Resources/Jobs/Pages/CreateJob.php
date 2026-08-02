<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\Concerns\GuardsJobPlanLimit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJob extends CreateRecord
{
    use GuardsJobPlanLimit;

    protected static string $resource = JobResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $this->ensureJobCanBeSaved($data);

        return parent::handleRecordCreation($data);
    }
}
