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
            $table->timestamps();

            $table->unique(['job_id', 'candidate_id']);
            $table->index(['job_id', 'status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
