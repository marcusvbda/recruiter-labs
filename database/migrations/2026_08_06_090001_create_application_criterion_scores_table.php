<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_criterion_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('criterion');
            $table->unsignedTinyInteger('weight');
            $table->unsignedTinyInteger('score');
            $table->text('reason');
            $table->timestamps();

            $table->index(['company_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_criterion_scores');
    }
};
