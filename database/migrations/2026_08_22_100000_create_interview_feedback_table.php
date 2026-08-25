<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Human evidence observed during an interview, stored apart from the AI
     * evaluation it complements. It never writes into
     * `application_criterion_scores`, `applications.analysis_*` or the interview
     * brief: what the application showed and what a person observed are two
     * different claims and the recruiter has to be able to tell them apart.
     *
     * Every row is attributable by construction — author, interview,
     * application and submission time are all columns, none of them nullable —
     * because feedback nobody can be asked about is not evidence.
     *
     * `application_id` and `job_id` are denormalised from the interview so the
     * application workspace can read "all interview evidence for this candidate"
     * without joining through interviews, and so a criterion can be checked
     * against the interviewed job cheaply.
     *
     * `criteria_generation` records the job's criteria revision at submission
     * time. Together with the per-criterion snapshot in
     * `interview_feedback_criteria`, it means a later criteria edit cannot
     * rewrite what the interviewer originally evaluated.
     *
     * The unique key is (interview, author), not (interview): two interviewers
     * on the same interview keep independent records that never replace each
     * other, while one interviewer correcting their own feedback updates the
     * record they already submitted instead of accumulating drafts.
     */
    public function up(): void
    {
        Schema::create('interview_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            // The author must never be erasable into anonymity: deleting the
            // user is restricted rather than nulling the attribution.
            $table->foreignId('submitted_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->unsignedInteger('criteria_generation');
            $table->text('general_note')->nullable();
            $table->timestamps();

            $table->unique(['interview_id', 'submitted_by_id']);
            $table->index(['application_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_feedback');
    }
};
