<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use \Illuminate\Auth\MustVerifyEmail;

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('role', 'access_disabled_at')
            ->withTimestamps();
    }

    /**
     * The workspaces this user may enter right now: membership plus access. An
     * Owner membership always grants it, so the invariant holds even against
     * hand-edited data.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companiesWithWorkspaceAccess(): BelongsToMany
    {
        return $this->companies()->where(function (Builder $query): void {
            $query
                ->whereNull('company_user.access_disabled_at')
                ->orWhere('company_user.role', CompanyRole::Owner->value);
        });
    }

    public function roleIn(Company $company): ?CompanyRole
    {
        return $company->roleFor($this);
    }

    public function isOwnerOf(Company $company): bool
    {
        return $company->isOwner($this);
    }

    public function hasWorkspaceAccessTo(Company $company): bool
    {
        return $company->hasWorkspaceAccess($this);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Only the workspaces this user may actually enter, so one whose access was
     * disabled disappears from the workspace switcher. Queried rather than read
     * from a loaded `companies` relation, which would still list a workspace the
     * user lost access to earlier in the same request.
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->companiesWithWorkspaceAccess()->get();
    }

    /**
     * `Filament\Http\Middleware\IdentifyTenant` calls this on every request and
     * aborts 404 when it is false, so deciding it from membership *and* access is
     * what makes losing access immediate — for a direct URL, a remembered
     * workspace and every workspace action alike.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companiesWithWorkspaceAccess()->whereKey($tenant)->exists();
    }

    public function planChanges(): HasMany
    {
        return $this->hasMany(PlanChange::class, 'changed_by_id');
    }

    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    /** @return HasMany<ConnectedIntegration, $this> */
    public function connectedIntegrations(): HasMany
    {
        return $this->hasMany(ConnectedIntegration::class);
    }

    /** @return HasMany<Interview, $this> */
    public function calendarInterviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'calendar_user_id');
    }
}
