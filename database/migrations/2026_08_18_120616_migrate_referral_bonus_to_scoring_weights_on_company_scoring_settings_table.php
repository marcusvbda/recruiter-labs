<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            ! Schema::hasTable('company_scoring_settings')
            || ! Schema::hasColumn('company_scoring_settings', 'referral_bonus_percentage')
        ) {
            return;
        }

        if (! Schema::hasColumn('company_scoring_settings', 'analysis_weight')) {
            Schema::table('company_scoring_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('analysis_weight')->default(60);
            });
        }

        if (! Schema::hasColumn('company_scoring_settings', 'referral_weight')) {
            Schema::table('company_scoring_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('referral_weight')->default(40);
            });
        }

        DB::table('company_scoring_settings')
            ->select(['id', 'referral_bonus_percentage'])
            ->orderBy('id')
            ->chunkById(100, function ($settings): void {
                foreach ($settings as $setting) {
                    $referralWeight = min(
                        100,
                        max(0, (int) data_get($setting, 'referral_bonus_percentage', 40)),
                    );

                    DB::table('company_scoring_settings')
                        ->where('id', data_get($setting, 'id'))
                        ->update([
                            'analysis_weight' => 100 - $referralWeight,
                            'referral_weight' => $referralWeight,
                        ]);
                }
            });

        Schema::table('company_scoring_settings', function (Blueprint $table): void {
            $table->dropColumn('referral_bonus_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('company_scoring_settings')) {
            return;
        }

        if (! Schema::hasColumn('company_scoring_settings', 'referral_bonus_percentage')) {
            Schema::table('company_scoring_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('referral_bonus_percentage')->default(40);
            });
        }

        $hasReferralWeight = Schema::hasColumn('company_scoring_settings', 'referral_weight');
        $hasAnalysisWeight = Schema::hasColumn('company_scoring_settings', 'analysis_weight');

        if ($hasReferralWeight || $hasAnalysisWeight) {
            $columns = ['id'];

            if ($hasReferralWeight) {
                $columns[] = 'referral_weight';
            }

            if ($hasAnalysisWeight) {
                $columns[] = 'analysis_weight';
            }

            DB::table('company_scoring_settings')
                ->select($columns)
                ->orderBy('id')
                ->chunkById(100, function ($settings) use ($hasReferralWeight): void {
                    foreach ($settings as $setting) {
                        $referralBonusPercentage = $hasReferralWeight
                            ? (int) data_get($setting, 'referral_weight', 40)
                            : 100 - (int) data_get($setting, 'analysis_weight', 60);

                        DB::table('company_scoring_settings')
                            ->where('id', data_get($setting, 'id'))
                            ->update([
                                'referral_bonus_percentage' => min(100, max(0, $referralBonusPercentage)),
                            ]);
                    }
                });
        }

        if ($hasAnalysisWeight) {
            Schema::table('company_scoring_settings', function (Blueprint $table): void {
                $table->dropColumn('analysis_weight');
            });
        }

        if ($hasReferralWeight) {
            Schema::table('company_scoring_settings', function (Blueprint $table): void {
                $table->dropColumn('referral_weight');
            });
        }
    }
};
