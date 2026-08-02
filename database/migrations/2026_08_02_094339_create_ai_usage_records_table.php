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
        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation');
            $table->string('provider');
            $table->string('model');
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cached_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status');
            $table->boolean('used_own_key')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'used_own_key', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
