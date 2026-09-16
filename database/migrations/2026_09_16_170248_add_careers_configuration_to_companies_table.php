<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('careers_enabled')->default(false)->after('plan_id');
            $table->text('careers_description')->nullable()->after('careers_enabled');
            $table->string('careers_logo_path')->nullable()->after('careers_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'careers_enabled',
                'careers_description',
                'careers_logo_path',
            ]);
        });
    }
};
