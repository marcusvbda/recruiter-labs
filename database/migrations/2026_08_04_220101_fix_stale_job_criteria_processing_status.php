<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Jobs that reached extraction never had their generation advanced past 0.
        // Any row still at generation 0 was never actually queued, regardless of
        // what its status column says (e.g. rows created before this feature existed).
        DB::table('job_postings')
            ->where('criteria_generation', 0)
            ->update(['criteria_processing_status' => 'not_started']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: the original (incorrect) status values are not recoverable.
    }
};
