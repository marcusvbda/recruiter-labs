<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `is_hired` already marks the definitive hired stage, but nothing tells the
     * product which stages are "close to hire". Inferring that from stage names
     * ("Final interview", "Offer") is unreliable across workspaces and locales,
     * so the workflow declares it explicitly instead.
     */
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->boolean('is_final_stage')->default(false)->after('is_hired');
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropColumn('is_final_stage');
        });
    }
};
