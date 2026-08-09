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
        Schema::create('recruitment_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_setting_id')->nullable()->constrained('company_email_provider_settings')->nullOnDelete();
            $table->string('provider');
            $table->string('idempotency_key', 128)->unique();
            $table->string('status');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->string('last_exception_class')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recruitment_email_deliveries');
    }
};
