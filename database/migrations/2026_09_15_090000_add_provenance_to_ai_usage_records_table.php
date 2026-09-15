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
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->string('origin')->default('user_requested')->after('operation');
            $table->string('trigger')->nullable()->after('origin');

            $table->index(['company_id', 'origin', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'origin', 'created_at']);
            $table->dropColumn(['origin', 'trigger']);
        });
    }
};
