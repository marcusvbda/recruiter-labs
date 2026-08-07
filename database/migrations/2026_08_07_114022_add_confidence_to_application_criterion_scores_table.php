<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('application_criterion_scores', function (Blueprint $table): void {
            // Default covers rows created before this column existed; every row written
            // going forward always supplies an explicit value (enforced by
            // ReplaceApplicationFitAnalysis's validation), so this is a backfill-safety
            // net, not a real fallback used in practice.
            $table->string('confidence')->default('medium')->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_criterion_scores', function (Blueprint $table): void {
            $table->dropColumn('confidence');
        });
    }
};
