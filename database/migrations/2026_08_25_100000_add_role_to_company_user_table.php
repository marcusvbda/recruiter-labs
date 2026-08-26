<?php

use App\Enums\CompanyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The default keeps every membership created without pivot data — factories,
     * seeders and existing code paths — a plain Member. Ownership is always an
     * explicit decision.
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->string('role')->default(CompanyRole::Member->value)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
