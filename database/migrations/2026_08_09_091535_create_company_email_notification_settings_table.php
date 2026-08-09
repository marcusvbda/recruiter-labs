<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_email_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_email_notification_settings');
    }
};
