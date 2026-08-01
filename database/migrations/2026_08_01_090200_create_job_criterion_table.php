<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Explicit pivot table (not Eloquent's auto-alphabetized pivot naming)
        // because it backs a real `JobCriterion` Pivot model with its own
        // auto-incrementing primary key and extra `weight` column.
        Schema::create('job_criterion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('weight');
            $table->timestamps();

            $table->unique(['job_id', 'criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_criterion');
    }
};
