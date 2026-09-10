<?php

namespace App\Jobs;

use App\Services\CandidateMaterialPreparation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PrepareCandidateMaterial implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public int $companyId, public int $materialId, public int $generation) {}

    public function handle(CandidateMaterialPreparation $preparation): void
    {
        $preparation->prepare($this->companyId, $this->materialId, $this->generation);
    }

    public function failed(?Throwable $exception): void
    {
        app(CandidateMaterialPreparation::class)->fail($this->companyId, $this->materialId, $this->generation, 'preparation_failed');
    }
}
