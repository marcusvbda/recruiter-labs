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
            $table->foreignId('job_id')->nullable()->after('application_id')->constrained('job_postings')->nullOnDelete();
            $table->uuid('execution_id')->nullable()->after('job_id');
            $table->unsignedSmallInteger('attempt')->default(1)->after('execution_id');
            $table->string('ai_provider')->nullable()->after('provider');
            $table->unsignedBigInteger('total_tokens')->default(0)->after('output_tokens');

            $table->index(['job_id', 'created_at']);
            $table->unique(['execution_id', 'attempt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropIndex(['job_id', 'created_at']);
            $table->dropConstrainedForeignId('job_id');
            $table->dropUnique(['execution_id', 'attempt']);
            $table->dropColumn(['execution_id', 'attempt', 'ai_provider', 'total_tokens']);
        });
    }
};
