<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pending offer to join a workspace, kept apart from membership: a row
     * here is not access, it only becomes access once `accepted_at` is set and
     * a `company_user` membership is created.
     *
     * The unique key is (company_id, email), not (company_id, email, state).
     * That is the design: "at most one usable pending invitation per workspace
     * and normalized email" is a database guarantee instead of application
     * bookkeeping. Resending, revoking and re-inviting the same address all
     * reuse this one row rather than accumulating competing invitations.
     *
     * `email` is always stored normalized (lower-cased, trimmed) by
     * `CompanyInvitation::normalizeEmail()`. Because the column holds the
     * normalized form, callers must reach a row through
     * `CompanyInvitation::scopeForEmail()`, which normalizes the input before
     * comparing; a raw `where('email', $input)` can miss the existing row and
     * turn a resend into a constraint violation.
     *
     * Only the SHA-256 of the token is stored. The plaintext exists once, in
     * the invitation URL that was emailed, so a leaked database cannot be used
     * to accept invitations.
     */
    public function up(): void
    {
        Schema::create('company_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash')->unique();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
    }
};
