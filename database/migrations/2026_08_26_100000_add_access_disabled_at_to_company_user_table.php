<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable with no default, because null means access is enabled: every
     * membership that already exists keeps access the moment the column appears,
     * and nobody loses it as a side effect of this change. The timestamp also
     * records when access was disabled, the way an invitation derives its state
     * from its own timestamps instead of a second boolean column.
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->timestamp('access_disabled_at')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('access_disabled_at');
        });
    }
};
