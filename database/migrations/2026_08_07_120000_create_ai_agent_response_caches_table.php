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
        Schema::create('ai_agent_response_caches', function (Blueprint $table): void {
            $table->id();
            $table->string('agent');
            $table->string('model');
            $table->string('request_hash', 64);
            $table->json('response');
            $table->timestamps();

            $table->unique(['agent', 'request_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_response_caches');
    }
};
