<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "How long has this candidate been waiting here?" is a workflow question,
     * and `updated_at` cannot answer it: an evaluation finishing, an interview
     * being booked or any metadata write touches it without the candidate having
     * moved. The moment of entry into the current stage is therefore recorded
     * explicitly, written only by the status-movement path.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestamp('status_entered_at')->nullable()->after('status_id');
        });

        // Pre-launch backfill, run once. Exact historical stage entry is not
        // recoverable, so `updated_at` is used as the closest available upper
        // bound: it is never earlier than the real movement, which means a
        // backfilled row can be reported as *younger* than it truly is, but
        // never falsely reported as overdue. Applications that never moved have
        // `updated_at` equal to `created_at`, where the value is exact.
        DB::table('applications')
            ->whereNull('status_entered_at')
            ->update(['status_entered_at' => DB::raw('COALESCE(updated_at, created_at)')]);

        Schema::table('applications', function (Blueprint $table): void {
            $table->index(['company_id', 'status_entered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'status_entered_at']);
            $table->dropColumn('status_entered_at');
        });
    }
};
