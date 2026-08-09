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
        Schema::table('company_email_provider_settings', function (Blueprint $table) {
            $table->foreignId('connected_integration_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_email_provider_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connected_integration_id');
        });
    }
};
