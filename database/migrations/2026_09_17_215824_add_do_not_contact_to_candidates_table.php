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
        Schema::table('candidates', function (Blueprint $table): void {
            $table->timestamp('do_not_contact_at')->nullable()->index();
            $table->foreignId('do_not_contact_by_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('do_not_contact_by_id');
            $table->dropIndex(['do_not_contact_at']);
            $table->dropColumn('do_not_contact_at');
        });
    }
};
