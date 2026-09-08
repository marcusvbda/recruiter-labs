<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'email', 'phone', 'socials'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }

    public function setEmailAttribute(?string $email): void
    {
        $this->attributes['email'] = $email === null
            ? null
            : mb_strtolower(trim($email));
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
}
