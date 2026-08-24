<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An evaluation belongs to the criteria revision that produced it. Without
     * that link, a completed analysis keeps presenting itself as current after
     * the job's criteria changed — a stale fit shown as fact.
     *
     * `analysis_coverage` is how much of the weighted criteria the supplied
     * application actually allowed to be assessed. It is deliberately separate
     * from `analysis_score`: unknown evidence lowers coverage, never fit.
     *
     * Both are null for evaluations produced before this sprint: their revision
     * and coverage are genuinely unknown, and inventing either would be worse
     * than admitting it.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('analysis_criteria_generation')->nullable()->after('analysis_generation');
            $table->unsignedTinyInteger('analysis_coverage')->nullable()->after('analysis_score');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['analysis_criteria_generation', 'analysis_coverage']);
        });
    }
};
