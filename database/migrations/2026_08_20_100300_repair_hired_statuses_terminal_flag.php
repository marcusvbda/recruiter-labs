<?php

use App\Models\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A hired stage is always a finished outcome. {@see Status} has
     * enforced that on save since the flag was introduced, but rows written
     * before the hook existed — and never saved through the model since — kept
     * `is_hired = true, is_terminal = false`.
     *
     * That combination is not merely untidy: `Application::scopeInProcess()`
     * keys on `is_terminal`, so a hired candidate would still count as "being
     * recruited" and could be raised as waiting for a decision. This re-asserts
     * the invariant, exactly as the migration that introduced `is_terminal` did.
     */
    public function up(): void
    {
        DB::table('statuses')
            ->where('is_hired', true)
            ->update(['is_terminal' => true, 'is_final_stage' => false]);

        // A finished outcome is never also an active finalist stage.
        DB::table('statuses')
            ->where('is_terminal', true)
            ->update(['is_final_stage' => false, 'attention_after_days' => null]);
    }

    /**
     * Not reversible: the pre-migration state was invalid, so there is nothing
     * meaningful to restore.
     */
    public function down(): void {}
};
