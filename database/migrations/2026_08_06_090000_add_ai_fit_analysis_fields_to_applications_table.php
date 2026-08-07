<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('analysis_generation')->default(0)->after('analysis_status');
            $table->decimal('analysis_score', 5, 2)->nullable()->after('analysis_generation');
            $table->timestamp('analyzed_at')->nullable()->after('analysis_score');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['analysis_generation', 'analysis_score', 'analyzed_at']);
        });
    }
};
