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
        Schema::create('job_review_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('category', 80);
            $table->string('severity', 20);
            $table->string('excerpt', 220)->nullable();
            $table->string('issue', 220);
            $table->string('suggestion', 220);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->index(['company_id', 'job_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_review_alerts');
    }
};
