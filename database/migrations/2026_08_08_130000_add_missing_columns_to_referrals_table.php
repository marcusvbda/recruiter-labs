<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `create_referrals_table` already ran before `published`, `expires_at`,
     * and `max_applications` were added to that migration file, so the live
     * table never picked them up. Backfilling them here instead of editing
     * the already-run migration.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->boolean('published')->default(true)->after('key');
            $table->timestamp('expires_at')->nullable()->after('published');
            $table->unsignedInteger('max_applications')->default(1)->after('expires_at');

            $table->index(['published', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['published', 'expires_at']);
            $table->dropColumn(['published', 'expires_at', 'max_applications']);
        });
    }
};
