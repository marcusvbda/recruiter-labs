<?php

namespace App\Models;

use App\Services\CandidateMaterialErasure;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $email
 * @property string|null $normalized_email
 * @property string|null $phone
 * @property int $materials_revision
 * @property Carbon|null $do_not_contact_at
 * @property int|null $do_not_contact_by_id
 */
#[Fillable(['company_id', 'name', 'email', 'phone', 'socials'])]
class Candidate extends Model
{
    protected $attributes = ['materials_revision' => 0];

    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'socials' => 'array',
            'materials_revision' => 'integer',
            'do_not_contact_at' => 'datetime',
        ];
    }

    /**
     * Workspace email identity is guaranteed by the unique index on
     * (company_id, normalized_email). The pre-check exists only to report a
     * losing race as a field error instead of a driver integrity exception, so
     * no workspace-wide write lock is needed here.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $normalized = $this->normalized_email;
            if ($normalized !== null && $normalized !== '' && isset($this->attributes['company_id'])
                && (! $this->exists || $this->isDirty(['email', 'normalized_email', 'company_id']))) {
                $matches = static::matchingEmail($this->company_id, $normalized);
                if ($this->exists) {
                    $matches->whereKeyNot($this->getKey());
                }
                if ($matches->exists()) {
                    throw $this->duplicateEmail();
                }
            }

            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException) {
                throw $this->duplicateEmail();
            }
        });
    }

    private function duplicateEmail(): ValidationException
    {
        return ValidationException::withMessages(['email' => 'This workspace already has a candidate with this email.']);
    }

    protected static function booted(): void
    {
        static::created(function (Candidate $candidate): void {
            Company::query()->whereKey($candidate->company_id)->increment('candidate_pool_revision');
        });
        static::deleting(function (Candidate $candidate): void {
            app(CandidateMaterialErasure::class)->eraseCandidate($candidate);
        });
    }

    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            Company::query()->whereKey($this->company_id)->lockForUpdate()->firstOrFail();

            return parent::delete();
        });
    }

    /** @return HasMany<CandidateMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(CandidateMaterial::class)->whereNull('deleted_at');
    }

    /** @return HasOne<CandidateImportOrigin, $this> */
    public function importOrigin(): HasOne
    {
        return $this->hasOne(CandidateImportOrigin::class);
    }

    /**
     * Both columns are written together so the unique index on
     * (company_id, normalized_email) always sees the PHP normalization.
     */
    public function setEmailAttribute(?string $email): void
    {
        $normalized = $email === null ? null : self::normalizeEmail($email);
        $this->attributes['email'] = $normalized;
        $this->attributes['normalized_email'] = blank($normalized) ? null : $normalized;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Indexed lookup on the stored normalization. Database LOWER/TRIM semantics
     * differ by driver, so the comparison happens against the column written by
     * {@see setEmailAttribute()} with exactly the same PHP normalization as new
     * writes. The unique index makes at most one row match.
     *
     * @return Builder<static>
     */
    public static function matchingEmail(int $companyId, string $email): Builder
    {
        return static::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('normalized_email', self::normalizeEmail($email));
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Jobs this candidate has been suggested for by sourcing. A suggestion is
     * not a hiring-process position: it says nothing about whether the candidate
     * ever applied, and carries none of an {@see Application}'s workflow state.
     *
     * @return HasMany<SourcingMatch, $this>
     */
    public function sourcingMatches(): HasMany
    {
        return $this->hasMany(SourcingMatch::class);
    }

    public function isDoNotContact(): bool
    {
        return $this->do_not_contact_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function doNotContactBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'do_not_contact_by_id');
    }

    /** @return HasMany<CandidateCommunicationThread, $this> */
    public function communicationThreads(): HasMany
    {
        return $this->hasMany(CandidateCommunicationThread::class);
    }
}
