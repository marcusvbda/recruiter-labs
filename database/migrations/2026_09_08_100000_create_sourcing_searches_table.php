<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One sourcing search per job: the job either has a current picture of its
     * workspace candidates or it does not, so `job_id` is unique and a rerun
     * replaces the same row rather than accumulating history that would need a
     * "which one is the real one" rule.
     *
     * `criteria_generation` records the criteria revision the stored result was
     * produced against, exactly like `applications.analysis_criteria_generation`.
     * It is nullable until the first run, because "no search has happened yet"
     * is not the same claim as "a search ran against revision 0".
     *
     * `generation` mirrors `applications.analysis_generation`: it identifies the
     * search *request*, so a queued run can tell whether it is still the one the
     * recruiter asked for.
     *
     * The summary counts are nullable for the same reason: before a run
     * completes, how many candidates were considered is unknown, and 0 would be
     * a fabricated answer.
     */
    public function up(): void
    {
        Schema::create('sourcing_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->unique()->constrained('job_postings')->cascadeOnDelete();
            $table->unsignedBigInteger('criteria_generation')->nullable();
            $table->unsignedBigInteger('generation')->default(0);
            $table->string('status')->default('not_started');
            $table->unsignedInteger('candidates_considered')->nullable();
            $table->unsignedInteger('matches_found')->nullable();
            $table->unsignedInteger('insufficient_count')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_searches');
    }
};
