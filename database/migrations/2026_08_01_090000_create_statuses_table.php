<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            // `company_id` is denormalized from the pipeline (as everywhere else in
            // this schema) purely so tenant scoping never needs a join. The pipeline
            // is the owner: statuses belong to a workflow, not to a company at large.
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_hired')->default(false);
            $table->boolean('sends_email')->default(false);
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->timestamps();

            $table->unique(['pipeline_id', 'name']);
            $table->index(['pipeline_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
