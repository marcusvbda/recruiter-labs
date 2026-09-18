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
        Schema::table('candidate_communication_messages', function (Blueprint $table): void {
            // Nullable preserves existing history as unknown provenance. New
            // records are assigned explicitly by their creation path.
            $table->string('kind')->nullable()->after('status');
            $table->index(['company_id', 'thread_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_communication_messages', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'thread_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
