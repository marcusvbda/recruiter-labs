<?php

namespace App\Services;

use App\Models\CandidateFileCleanup;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CandidatePrivateFileCleanup
{
    /** Persist before removing an original reference, in its transaction. */
    public function remember(int $companyId, string $path): CandidateFileCleanup
    {
        return CandidateFileCleanup::query()->firstOrCreate(['company_id' => $companyId, 'path' => $path]);
    }

    /** The retention scheduler may retry this same obligation after failure. */
    public function run(CandidateFileCleanup $cleanup): bool
    {
        try {
            if (! Storage::disk('candidate_materials')->delete($cleanup->path)) {
                throw new \RuntimeException('Private file cleanup failed.');
            }
            $cleanup->delete();

            return true;
        } catch (Throwable) {
            $cleanup->forceFill(['attempts' => $cleanup->attempts + 1, 'failed_at' => now()])->save();

            return false;
        }
    }
}
