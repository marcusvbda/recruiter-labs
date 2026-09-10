<?php

use App\Services\CandidateMaterialRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sourcing result must describe the material that actually existed when it
     * was produced, not only the job's criteria at that time.
     *
     * `sourcing_matches.materials_revision` snapshots the candidate's
     * `candidates.materials_revision` at the moment the assessment was persisted.
     * That counter is advanced by {@see CandidateMaterialRevision}
     * whenever a material becomes readable, is archived, restored, deleted,
     * becomes unavailable, or has its declared received date corrected — exactly
     * the events the spec lists as invalidating an analysis. Comparing the
     * snapshot with the live counter on read makes *only* the affected
     * candidate's matches outdated; every other candidate's analysis is
     * untouched, because their own counter did not move.
     *
     * `sourcing_searches.candidate_pool_revision` does the same for the pool as a
     * whole: a new contact or a newly readable CV advances
     * `companies.candidate_pool_revision`, so a completed search can tell the
     * recruiter its coverage predates the current pool until they refresh.
     *
     * Both are nullable. A result recorded before this column existed cannot
     * claim a revision it never captured, and 0 would be a fabricated snapshot
     * that happened to look current for a brand-new workspace.
     */
    public function up(): void
    {
        Schema::table('sourcing_matches', function (Blueprint $table): void {
            $table->unsignedBigInteger('materials_revision')->nullable()->after('criteria_generation');
        });

        Schema::table('sourcing_searches', function (Blueprint $table): void {
            $table->unsignedBigInteger('candidate_pool_revision')->nullable()->after('criteria_generation');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_matches', function (Blueprint $table): void {
            $table->dropColumn('materials_revision');
        });

        Schema::table('sourcing_searches', function (Blueprint $table): void {
            $table->dropColumn('candidate_pool_revision');
        });
    }
};
