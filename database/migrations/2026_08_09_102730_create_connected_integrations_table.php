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
        Schema::create('connected_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_key');
            $table->string('status');
            $table->string('external_account_id')->nullable();
            $table->string('account_email')->nullable();
            $table->string('account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'plugin_key']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_integrations');
    }
};
