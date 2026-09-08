<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (job, candidate): rerunning a search refreshes the existing
     * row instead of producing a second opinion about the same pairing, which
     * the unique constraint enforces at the database level.
     *
     * `potential_match` and `evidence_coverage` are nullable and separate on
     * purpose. Null means the candidate's material did not support a judgement
     * at all — it is not 0 and not a low match. Coverage is assessed weight over
     * total weight and never gets folded into the match figure.
     *
     * `criteria_generation` is the staleness link, same pattern as
     * `applications.analysis_criteria_generation`: a match measured against an
     * earlier criteria revision is history, not the current assessment.
     *
     * `state` is deliberately limited to suggested/saved/dismissed. Whether the
     * candidate is already in the job is *not* stored here — it is computed from
     * `applications`, so a candidate who arrived through any other path (manual
     * add, direct application) is recognised without a flag anyone has to keep
     * in sync.
     *
     * The candidate FK cascades exactly like `applications.candidate_id`: a
     * deleted candidate must stop being actionable anywhere in the workspace.
     */
    public function up(): void
    {
        Schema::create('sourcing_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('criteria_generation');
            $table->unsignedTinyInteger('potential_match')->nullable();
            $table->unsignedTinyInteger('evidence_coverage')->nullable();
            $table->string('confidence')->nullable();
            $table->boolean('sufficient_information')->default(true);
            $table->string('state')->default('suggested');
            $table->timestamp('state_changed_at')->nullable();
            $table->foreignId('state_changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'candidate_id']);
            $table->index(['company_id', 'job_id']);
            $table->index(['job_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_matches');
    }
};
