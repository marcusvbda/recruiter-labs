<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-criterion breakdown behind a sourcing match, shaped exactly like
     * `application_criterion_scores`.
     *
     * `criterion` and `weight` are a text snapshot of the `JobCriterion` that
     * produced the row, resolved by ID at persistence time — not a live foreign
     * key. The stored result must keep describing what was actually measured
     * even after the criterion is edited or deleted.
     *
     * `score` is nullable: "the material does not support a judgement here" is a
     * real answer, and it is not 0, not 50 and not a failure. It lowers evidence
     * coverage and stays out of the match figure entirely.
     *
     * `evidence` holds at most three `{source, detail}` items so the support for
     * an assessment is something a recruiter can check rather than take on trust.
     *
     * Unlike the Application analogue, `confidence` carries no default: there is
     * no pre-existing data to back-fill, and every write supplies it explicitly.
     */
    public function up(): void
    {
        Schema::create('sourcing_match_criterion_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sourcing_match_id')->constrained()->cascadeOnDelete();
            $table->string('criterion');
            $table->unsignedTinyInteger('weight');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('reason');
            $table->string('confidence');
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sourcing_match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_match_criterion_scores');
    }
};
