<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Waiting too long" is not a universal number of days: a workspace that
     * screens in a day and one that screens in a fortnight are both healthy.
     * The expectation therefore belongs to the workflow stage, not to
     * application code, and stays optional — a stage without a threshold never
     * produces an age-based warning.
     */
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attention_after_days')->nullable()->after('is_terminal');
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropColumn('attention_after_days');
        });
    }
};
