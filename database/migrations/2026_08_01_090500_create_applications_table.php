<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            // A status with applications must not be silently deletable.
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('direct');
            $table->string('analysis_status')->default('pending');
            $table->string('cover_letter_type')->default('none');
            $table->text('cover_letter_text')->nullable();
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'candidate_id']);
            $table->index(['job_id', 'status_id']);
            $table->index(['company_id', 'analysis_status']);
        });

        Schema::create('application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_snapshot');
            $table->string('response_type');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 4)->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'job_application_question_id']);
            $table->index(['company_id', 'application_id']);
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['application_id', 'type']);
            $table->index(['company_id', 'application_id']);
        });

        Schema::create('application_utm_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('value');

            $table->unique(['application_id', 'name']);
            $table->index(['name', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_utm_parameters');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_answers');
        Schema::dropIfExists('applications');
    }
};
