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
        Schema::create('application_interview_brief_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_criterion_score_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('criterion', 220);
            $table->string('priority', 20);
            $table->string('reason', 220);
            $table->string('question', 300);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->index(['company_id', 'application_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_interview_brief_items');
    }
};
