<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->unsignedBigInteger('candidate_pool_revision')->default(0));
        Schema::table('candidates', fn (Blueprint $table) => $table->unsignedBigInteger('materials_revision')->default(0));
        Schema::create('candidate_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executing_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_label', 120);
            $table->string('status')->default('draft');
            $table->string('separator', 1)->default(',');
            $table->boolean('correction_mode')->default(false);
            $table->string('disk')->default('candidate_materials');
            $table->string('csv_path')->nullable();
            $table->string('csv_original_name')->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->unsignedBigInteger('validated_revision')->nullable();
            $table->unsignedBigInteger('confirmed_revision')->nullable();
            $table->unsignedBigInteger('execution_generation')->default(0);
            $table->foreignId('executing_company_id')->nullable()->unique()->constrained('companies')->cascadeOnDelete();
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('declaration_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('last_progress_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('details_expired_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->index(['company_id', 'status']);
            $table->timestamps();
        });
        Schema::create('candidate_import_origins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('candidate_import_batches')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_label', 120);
            $table->timestamp('added_at');
            $table->timestamps();
        });
        Schema::create('candidate_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('candidate_import_batches')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('metadata_corrected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('candidate_materials');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('extension', 5);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->string('source_label', 120)->nullable();
            $table->date('received_on')->nullable();
            $table->timestamp('added_at');
            $table->timestamp('declaration_at');
            $table->timestamp('metadata_corrected_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('preparation_started_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('cleanup_failed_at')->nullable();
            $table->string('preparation_status')->default('stored');
            $table->unsignedBigInteger('preparation_generation')->default(0);
            $table->string('preparation_error')->nullable();
            $table->longText('prepared_text')->nullable();
            $table->boolean('text_is_partial')->default(false);
            $table->unique(['company_id', 'candidate_id', 'checksum'], 'candidate_material_content_unique');
            $table->index(['company_id', 'candidate_id', 'deleted_at', 'archived_at'], 'candidate_material_lifecycle_index');
            $table->index(['preparation_status', 'preparation_started_at'], 'candidate_material_preparation_index');
            $table->timestamps();
        });
        Schema::create('candidate_import_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('candidate_import_batches')->cascadeOnDelete();
            $table->string('disk')->default('candidate_materials');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('association_name')->nullable();
            $table->string('extension', 5)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable()->index();
            $table->json('errors')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamp('cleanup_failed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('candidate_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('candidate_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('record_number');
            $table->json('payload')->nullable();
            $table->string('normalized_email')->nullable()->index();
            $table->foreignId('reviewed_candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('candidate_materials')->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('candidate_import_files')->nullOnDelete();
            $table->json('issues')->nullable();
            $table->json('decision')->nullable();
            $table->json('manifest')->nullable();
            $table->boolean('selected')->default(false);
            $table->string('status')->default('pending');
            $table->string('candidate_outcome')->nullable();
            $table->string('material_outcome')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('result_erased_at')->nullable();
            $table->unique(['batch_id', 'record_number']);
            $table->index(['company_id', 'batch_id', 'status']);
            $table->timestamps();
        });
        Schema::create('candidate_file_cleanups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('path')->unique();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_file_cleanups');
        Schema::dropIfExists('candidate_import_rows');
        Schema::dropIfExists('candidate_import_files');
        Schema::dropIfExists('candidate_materials');
        Schema::dropIfExists('candidate_import_origins');
        Schema::dropIfExists('candidate_import_batches');
        Schema::table('candidates', fn (Blueprint $table) => $table->dropColumn('materials_revision'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('candidate_pool_revision'));
    }
};
