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
        Schema::create('company_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('milestone');
            $table->timestamp('achieved_at');
            $table->timestamp('created_at')->useCurrent();

            // A milestone is reached once per workspace: this constraint, not
            // application code, is what makes recording it idempotent under
            // concurrent writes from model hooks and queued jobs.
            $table->unique(['company_id', 'milestone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_milestones');
    }
};
