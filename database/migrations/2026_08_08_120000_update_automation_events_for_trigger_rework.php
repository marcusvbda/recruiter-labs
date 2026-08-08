<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_events', function (Blueprint $table) {
            $table->unsignedBigInteger('automatable_id')->nullable()->change();
            $table->foreignId('status_id')->nullable()->after('automatable_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('automation_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
            $table->unsignedBigInteger('automatable_id')->nullable(false)->change();
        });
    }
};
