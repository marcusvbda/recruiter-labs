<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace's own estimate of how many minutes a recruiter used to spend
 * reviewing one application by hand.
 *
 * It is deliberately nullable and has no default: without an explicit number
 * from the workspace there is no honest way to state a time saving, and the
 * product shows measured counts only rather than inventing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('manual_review_minutes_per_application')
                ->nullable()
                ->after('candidate_pool_revision');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('manual_review_minutes_per_application');
        });
    }
};
