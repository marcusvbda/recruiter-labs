<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stage's email stops being a private copy of a subject and body and
     * becomes a reference to one reusable template.
     *
     * Everything a workspace already configured is carried over verbatim before
     * the old columns go away: one template per status that actually had
     * content, named after the status so a recruiter recognises it in Settings.
     * A status whose toggle was on but whose content was empty never sent
     * anything, so it keeps its toggle and simply has no template yet — the
     * migration disables nothing that used to work.
     */
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            // Restrict rather than cascade: a template in active use by a stage
            // must not disappear under the automation that depends on it.
            $table->foreignId('email_template_id')
                ->nullable()
                ->after('sends_email')
                ->constrained('email_templates')
                ->restrictOnDelete();
        });

        $this->backfillTemplates();

        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropColumn(['email_subject', 'email_body']);
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->string('email_subject')->nullable()->after('sends_email');
            $table->text('email_body')->nullable()->after('email_subject');
        });

        // Best effort: the per-status copy is rebuilt from whatever template the
        // status points at, which is exactly what it was migrated from.
        DB::table('statuses')
            ->whereNotNull('email_template_id')
            ->orderBy('id')
            ->each(function (object $status): void {
                $template = DB::table('email_templates')
                    ->where('id', $status->email_template_id)
                    ->first(['subject', 'body']);

                if (! $template instanceof stdClass) {
                    return;
                }

                DB::table('statuses')->where('id', $status->id)->update([
                    'email_subject' => $template->subject,
                    'email_body' => $template->body,
                ]);
            });

        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('email_template_id');
        });
    }

    private function backfillTemplates(): void
    {
        $now = now();

        DB::table('statuses')
            ->where('sends_email', true)
            ->whereNotNull('email_subject')
            ->where('email_subject', '<>', '')
            ->whereNotNull('email_body')
            ->where('email_body', '<>', '')
            ->orderBy('id')
            ->each(function (object $status) use ($now): void {
                $companyId = DB::table('pipelines')
                    ->where('id', $status->pipeline_id)
                    ->value('company_id') ?? $status->company_id;

                if ($companyId === null) {
                    return;
                }

                $templateId = DB::table('email_templates')->insertGetId([
                    'company_id' => $companyId,
                    'name' => $this->availableName((int) $companyId, $status->name.' stage email'),
                    'subject' => $status->email_subject,
                    'body' => $status->email_body,
                    'is_available' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('statuses')
                    ->where('id', $status->id)
                    ->update(['email_template_id' => $templateId]);
            });
    }

    /**
     * Template names are unique per workspace, and two stages may share a name
     * across pipelines, so a taken name gets a numeric suffix instead of
     * aborting the migration.
     */
    private function availableName(int $companyId, string $name): string
    {
        $name = mb_substr($name, 0, 255);
        $candidate = $name;
        $suffix = 1;

        while (DB::table('email_templates')->where('company_id', $companyId)->where('name', $candidate)->exists()) {
            $suffix++;
            $candidate = mb_substr($name, 0, 250).' '.$suffix;
        }

        return $candidate;
    }
};
