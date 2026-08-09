<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_email_provider_settings', function (Blueprint $table) {
            $table->string('from_address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_email_provider_settings', function (Blueprint $table) {
            $table->dropColumn('from_address');
        });
    }
};
