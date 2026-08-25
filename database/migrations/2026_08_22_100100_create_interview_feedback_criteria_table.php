<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One criterion's human result inside a submitted interview feedback.
     *
     * `criterion` and `weight` are a snapshot of the `job_criterion` row this
     * result was recorded against, resolved by ID at submission time. The
     * snapshot is the point: criteria are editable, and a recruiter rewording or
     * reweighting a criterion months later must not retroactively change what an
     * interviewer says they observed. `job_criterion_id` stays for as long as the
     * criterion exists so current criteria and historical feedback can still be
     * lined up, and nulls out when it is deleted — losing the link, never the
     * recorded observation.
     *
     * `result` is one of the four `App\Enums\InterviewFeedbackResult` cases and
     * is stored as a string, not a number. There is deliberately no score
     * column: an interview result that could be averaged would be an automatic
     * recalculation of candidate fit, and "not assessed" would become a zero.
     *
     * The unique key keeps one result per criterion within a submission, so a
     * correction replaces the previous answer rather than stacking a second,
     * contradictory one beside it.
     */
    public function up(): void
    {
        Schema::create('interview_feedback_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // The parent table is singular (`interview_feedback`), so the
            // conventional plural guess would point at a table that never exists.
            $table->foreignId('interview_feedback_id')->constrained('interview_feedback')->cascadeOnDelete();
            $table->foreignId('job_criterion_id')->nullable()->constrained('job_criterion')->nullOnDelete();
            $table->string('criterion');
            $table->unsignedInteger('weight');
            $table->string('result');
            $table->text('evidence_note')->nullable();
            $table->timestamps();

            // Named explicitly: the conventional name would exceed the 63-character
            // identifier limit and be silently truncated.
            $table->unique(['interview_feedback_id', 'job_criterion_id'], 'interview_feedback_criterion_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_feedback_criteria');
    }
};
