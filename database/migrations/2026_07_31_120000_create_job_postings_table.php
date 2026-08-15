<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_file_types', function (Blueprint $table) {
            $table->id();
            $table->string('extension')->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Table is named `job_postings` (not `jobs`) because Laravel's queue
        // system already owns the `jobs` table in this application.
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // A pipeline in use by a job must not be silently deletable: the job's
            // whole recruitment workflow (and every application's status) lives in it.
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('application_locale')->default('en');
            $table->text('description')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->uuid('key')->unique();
            $table->boolean('published')->default(false);
            $table->boolean('applications_paused')->default(false);
            $table->unsignedInteger('application_limit')->nullable();
            $table->boolean('cover_letter_required')->default(false);
            $table->string('cover_letter_type')->default('text');
            $table->timestamps();
        });

        Schema::create('job_application_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('question');
            $table->string('response_type');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['job_id', 'sort']);
        });

        Schema::create('cv_file_type_job', function (Blueprint $table) {
            $table->foreignId('cv_file_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();

            $table->primary(['cv_file_type_id', 'job_id']);
        });

        Schema::create('cover_letter_file_type_job', function (Blueprint $table) {
            $table->foreignId('cv_file_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();

            $table->primary(['cv_file_type_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_letter_file_type_job');
        Schema::dropIfExists('cv_file_type_job');
        Schema::dropIfExists('job_application_questions');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('cv_file_types');
    }
};
