<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI proposes evaluation criteria; a human confirms the ones that actually
     * govern candidate evaluation. "Are the criteria currently stored for this
     * job the criteria the recruiter confirmed?" must be answerable without
     * inferring anything from the existence of `job_criterion` rows, so the
     * confirmed revision is recorded explicitly.
     *
     * `criteria_generation` doubles as the criteria revision: it already
     * advances on every extraction request and on every manual edit, which is
     * exactly when a confirmation stops being valid.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->unsignedBigInteger('criteria_confirmed_generation')->nullable()->after('criteria_generation');
            $table->timestamp('criteria_confirmed_at')->nullable()->after('criteria_confirmed_generation');
            $table->foreignId('criteria_confirmed_by_id')
                ->nullable()
                ->after('criteria_confirmed_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Criteria produced before this gate existed were never human-confirmed,
        // and pretending otherwise would be a fake historical certainty. They
        // keep their criteria and move to review, so a recruiter confirms them
        // once before any further candidate evaluation runs against them.
        DB::table('job_postings')
            ->where('criteria_processing_status', 'completed')
            ->update(['criteria_processing_status' => 'awaiting_review']);
    }

    public function down(): void
    {
        DB::table('job_postings')
            ->where('criteria_processing_status', 'awaiting_review')
            ->update(['criteria_processing_status' => 'completed']);

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('criteria_confirmed_by_id');
            $table->dropColumn(['criteria_confirmed_generation', 'criteria_confirmed_at']);
        });
    }
};
