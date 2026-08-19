<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifies the scheduling request an interview came from, so replaying the
     * same submission (double click, retry after a timeout) reuses the interview
     * it already created instead of booking a second one. Scheduling another
     * interview deliberately uses a new key, so the constraint never blocks it.
     * Nullable: interviews created before this existed have no request of record.
     */
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table): void {
            $table->uuid('schedule_request_key')->nullable()->unique()->after('calendar_integration_id');
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table): void {
            $table->dropUnique(['schedule_request_key']);
            $table->dropColumn('schedule_request_key');
        });
    }
};
