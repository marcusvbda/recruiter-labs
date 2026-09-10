<?php

use App\Models\Candidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace email identity is enforced by the database, not by a workspace-wide
 * write lock. The column stores exactly what `Candidate::normalizeEmail()`
 * produces, because SQL `LOWER()`/`TRIM()` semantics differ per driver (SQLite
 * lowercases ASCII only, Postgres is locale-aware) and neither matches PHP
 * `mb_strtolower(trim())`. Normalizing in PHP keeps every driver identical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', fn (Blueprint $table) => $table->string('normalized_email')->nullable()->after('email'));

        $duplicates = [];
        $seen = [];
        foreach (DB::table('candidates')->select(['id', 'company_id', 'email'])->orderBy('id')->cursor() as $candidate) {
            $normalized = is_string($candidate->email) ? Candidate::normalizeEmail($candidate->email) : null;
            if ($normalized === null || $normalized === '') {
                continue;
            }
            DB::table('candidates')->where('id', $candidate->id)->update(['normalized_email' => $normalized]);
            $key = $candidate->company_id.'|'.$normalized;
            if (isset($seen[$key])) {
                $duplicates[] = $key;
            }
            $seen[$key] = true;
        }

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Cannot enforce workspace email identity: existing candidates already share a normalized email ('
                .implode(', ', array_unique($duplicates)).'). Merge them before migrating.'
            );
        }

        // Null normalized emails never collide: both SQLite and Postgres treat
        // NULL as distinct in a unique index, so email-less candidates stay
        // supported without a driver-specific partial index.
        Schema::table('candidates', fn (Blueprint $table) => $table->unique(['company_id', 'normalized_email']));
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'normalized_email']);
            $table->dropColumn('normalized_email');
        });
    }
};
