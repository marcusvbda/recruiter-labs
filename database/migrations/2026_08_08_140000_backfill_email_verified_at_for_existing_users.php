<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible: this migration only grandfathers in users who existed
     * before the required email-verification gate was introduced. Rolling
     * back would incorrectly mark real, already-verified accounts as
     * unverified again.
     */
    public function down(): void {}
};
