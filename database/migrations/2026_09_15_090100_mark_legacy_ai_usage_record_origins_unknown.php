<?php

use App\Enums\AiExecutionOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mark rows that existed before provenance was recorded without rewriting
     * newly created records, whose tracker writes include a meaningful trigger.
     */
    public function up(): void
    {
        DB::table('ai_usage_records')
            ->where('origin', AiExecutionOrigin::UserRequested->value)
            ->whereNull('trigger')
            ->update(['origin' => AiExecutionOrigin::LegacyUnknown->value]);
    }

    /**
     * Restore the value assigned by the provenance-column migration. This is
     * only relevant when rolling both provenance migrations back together.
     */
    public function down(): void
    {
        DB::table('ai_usage_records')
            ->where('origin', AiExecutionOrigin::LegacyUnknown->value)
            ->whereNull('trigger')
            ->update(['origin' => AiExecutionOrigin::UserRequested->value]);
    }
};
