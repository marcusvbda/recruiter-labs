<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activation progress belongs to the workspace and is shared by everyone in
     * it, but how the onboarding experience is *presented* is personal: one
     * member postponing the welcome or hiding the launcher must not change what
     * their colleagues see, and must never touch activation state. That makes
     * the membership row the right home for these two timestamps.
     *
     * Nullable with no default, because null means "not dismissed yet": every
     * membership that already exists keeps seeing the experience the moment the
     * columns appear. The timestamp also records when it was dismissed, instead
     * of a second boolean column.
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->timestamp('onboarding_welcome_dismissed_at')->nullable()->after('access_disabled_at');
            $table->timestamp('onboarding_launcher_hidden_at')->nullable()->after('onboarding_welcome_dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn(['onboarding_welcome_dismissed_at', 'onboarding_launcher_hidden_at']);
        });
    }
};
