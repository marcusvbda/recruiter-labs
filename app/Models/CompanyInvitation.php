<?php

namespace App\Models;

use App\Enums\CompanyInvitationStatus;
use Database\Factories\CompanyInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $company_id
 * @property string $email
 * @property string $token_hash
 * @property int|null $invited_by_id
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by_id
 * @property Carbon|null $revoked_at
 */
#[Fillable(['company_id', 'email', 'token_hash', 'invited_by_id', 'expires_at', 'accepted_at', 'accepted_by_id', 'revoked_at'])]
#[Hidden(['token_hash'])]
class CompanyInvitation extends Model
{
    /** @use HasFactory<CompanyInvitationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * One address is one identity. Both the write path and the lookup path go
     * through this single rule, so `Recruiter@Example.com` and
     * `recruiter@example.com` can never become two rows under the
     * (company_id, email) unique key.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = self::normalizeEmail($email);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    /**
     * The state is always derived from the persisted timestamps, never stored:
     * an invitation expires by the clock passing, with nothing writing to it.
     */
    public function status(): CompanyInvitationStatus
    {
        if ($this->revoked_at !== null) {
            return CompanyInvitationStatus::Revoked;
        }

        if ($this->accepted_at !== null) {
            return CompanyInvitationStatus::Accepted;
        }

        if ($this->expires_at->lessThanOrEqualTo(now())) {
            return CompanyInvitationStatus::Expired;
        }

        return CompanyInvitationStatus::Pending;
    }

    public function isPending(): bool
    {
        return $this->status() === CompanyInvitationStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->status() === CompanyInvitationStatus::Expired;
    }

    public function isRevoked(): bool
    {
        return $this->status() === CompanyInvitationStatus::Revoked;
    }

    public function isAccepted(): bool
    {
        return $this->status() === CompanyInvitationStatus::Accepted;
    }

    /**
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }

    /**
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->whereNotNull('accepted_at');
    }

    /**
     * The only correct way to look an address up: it normalizes before
     * comparing, the way {@see scopeForToken()} hashes before comparing. A raw
     * `where('email', $input)` would miss a stored row whose address was
     * spelled differently and lead the caller into a duplicate insert.
     *
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', self::normalizeEmail($email));
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * A plain unsalted SHA-256 is the right hash here: the token is 64 random
     * characters, not a password, so there is nothing to brute-force and the
     * lookup has to stay a single indexed comparison.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopeForToken(Builder $query, string $token): Builder
    {
        return $query->where('token_hash', self::hashToken($token));
    }

    /**
     * A raw lookup: it returns the invitation whatever state it is in. The
     * caller decides whether that invitation may still be used by checking
     * {@see status()} / {@see isPending()}.
     */
    public static function findByToken(string $token): ?self
    {
        return self::query()->forToken($token)->first();
    }
}
