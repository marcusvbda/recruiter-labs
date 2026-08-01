<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singular table name retained for the JobCriterion child model.
        Schema::create('job_criterion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('prompt', 150);
            $table->unsignedTinyInteger('weight');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_criterion');
    }
};
