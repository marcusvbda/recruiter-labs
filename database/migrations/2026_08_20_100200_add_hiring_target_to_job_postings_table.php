<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A job knows whether somebody was hired, but not whether the hiring
     * objective was met. `hiring_target` is how many hires the process aims to
     * produce — unrelated to `application_limit` (how many candidates may
     * apply) and unrelated to plan limits.
     *
     * Existing jobs default to a single opening, which is what a job without an
     * explicit target has always meant in the product's copy.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->unsignedInteger('hiring_target')->default(1)->after('application_limit');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropColumn('hiring_target');
        });
    }
};
