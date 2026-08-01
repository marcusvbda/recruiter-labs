<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('key')->unique();
            $table->timestamps();

            $table->unique(['job_id', 'user_id']);
        });

        Schema::create('job_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['job_id', 'created_at']);
            $table->index(['job_id', 'ip_address']);
        });

        Schema::create('job_click_utm_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_click_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('value');

            $table->unique(['job_click_id', 'name']);
            $table->index(['name', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_click_utm_parameters');
        Schema::dropIfExists('job_clicks');
        Schema::dropIfExists('referrals');
    }
};
