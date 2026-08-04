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
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('criteria_processing_status')->default('pending')->index();
            $table->unsignedBigInteger('criteria_generation')->default(0);
        });

        DB::table('job_postings')->update([
            'criteria_processing_status' => 'not_started',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['criteria_processing_status']);
            $table->dropColumn(['criteria_processing_status', 'criteria_generation']);
        });
    }
};
