<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            // HTML authored in a rich editor, like `statuses.email_body`.
            $table->text('body');
            // A retired template stays readable for history, but is no longer
            // offered when composing a message or configuring a stage.
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
