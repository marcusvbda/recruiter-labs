<?php

use App\Enums\CompanyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every workspace that already has members must end up with exactly one
     * Owner, without anyone losing access. The longest-standing membership is
     * promoted; everybody else stays a Member.
     *
     * `created_at IS NULL` is ordered explicitly because PostgreSQL and SQLite
     * disagree on where NULLs land by default, and the choice must be the same
     * in production and in the test database.
     */
    public function up(): void
    {
        $companyIds = DB::table('company_user')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $ownerCount = DB::table('company_user')
                ->where('company_id', $companyId)
                ->where('role', CompanyRole::Owner->value)
                ->count();

            if ($ownerCount === 1) {
                continue;
            }

            $membershipId = DB::table('company_user')
                ->where('company_id', $companyId)
                ->orderByRaw('created_at IS NULL, created_at ASC, id ASC')
                ->value('id');

            if ($membershipId === null) {
                continue;
            }

            // Demoting first makes a second Owner impossible even if the data
            // already contained more than one.
            DB::table('company_user')
                ->where('company_id', $companyId)
                ->update(['role' => CompanyRole::Member->value]);

            DB::table('company_user')
                ->where('id', $membershipId)
                ->update(['role' => CompanyRole::Owner->value]);
        }
    }

    /**
     * Not reversible: the pre-migration state had no notion of ownership, so
     * there is nothing meaningful to restore.
     */
    public function down(): void {}
};
