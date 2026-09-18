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
        Schema::create('candidate_communication_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('job_postings')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Candidate + Job is the V1 outreach identity. An application may
            // be attached later without splitting the candidate's history.
            $table->unique(['company_id', 'candidate_id', 'job_id']);
            $table->index(['company_id', 'application_id']);
        });

        Schema::create('candidate_communication_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('candidate_communication_threads')->cascadeOnDelete();
            $table->foreignId('authorized_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('authorized_by_name')->nullable();
            $table->foreignId('provider_setting_id')->nullable()->constrained('company_email_provider_settings')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->unique()->constrained('recruitment_email_deliveries')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->boolean('ai_assisted')->default(false);
            $table->string('draft_subject')->nullable();
            $table->longText('draft_body')->nullable();
            $table->string('authorized_subject')->nullable();
            $table->longText('authorized_body')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('provider')->nullable();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('send_requested_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'thread_id', 'status']);
            $table->index(['company_id', 'authorized_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_communication_messages');
        Schema::dropIfExists('candidate_communication_threads');
    }
};
