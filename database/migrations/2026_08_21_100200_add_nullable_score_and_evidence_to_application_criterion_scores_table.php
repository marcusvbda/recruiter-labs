<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Not enough information to assess this" is a real answer, and it is not
     * zero, not fifty and not a failure. A null `score` says the supplied
     * application does not support a fit judgement for that criterion; it lowers
     * evidence coverage and never fit.
     *
     * `evidence` records the concrete support found for the assessment, per
     * criterion, so "supported by application evidence" is a claim a recruiter
     * can check rather than take on trust.
     */
    public function up(): void
    {
        Schema::table('application_criterion_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('score')->nullable()->change();
            $table->json('evidence')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('application_criterion_scores', function (Blueprint $table): void {
            $table->dropColumn('evidence');
        });

        Schema::table('application_criterion_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('score')->nullable(false)->change();
        });
    }
};
