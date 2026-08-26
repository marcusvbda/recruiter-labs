<?php

namespace App\Models;

use App\Actions\ProvisionDefaultPipeline;
use App\Enums\CompanyRole;
use App\Enums\Feature;
use App\Enums\Limit;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'slug', 'plan_id'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // A company without a pipeline cannot post a job, so provisioning happens
        // once, here, rather than being repaired lazily from forms and services.
        static::created(function (Company $company): void {
            app(ProvisionDefaultPipeline::class)->handle($company);
        });
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'access_disabled_at')
            ->withTimestamps();
    }

    /**
     * A membership row exists only while the person is part of the workspace —
     * removal deletes it — so every membership is an active one. A member whose
     * workspace access is disabled is still one of them: they belong to the team
     * and stay listed there, they simply may not enter right now.
     *
     * @return BelongsToMany<User, $this>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->users();
    }

    /**
     * The memberships that may enter this workspace right now. Being on the team
     * and being allowed in are different questions: {@see activeMembers()}
     * answers the first, this answers the second.
     *
     * @return BelongsToMany<User, $this>
     */
    public function membersWithWorkspaceAccess(): BelongsToMany
    {
        return $this->users()->where(function (Builder $query): void {
            $query
                ->whereNull('company_user.access_disabled_at')
                ->orWhere('company_user.role', CompanyRole::Owner->value);
        });
    }

    public function owner(): ?User
    {
        return $this->users()->wherePivot('role', CompanyRole::Owner->value)->first();
    }

    /**
     * Read straight from the pivot table: callers run this right after a
     * membership changes, when a loaded `users` relation would still be stale.
     */
    public function roleFor(User $user): ?CompanyRole
    {
        $role = DB::table('company_user')
            ->where('company_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->value('role');

        return is_string($role) ? CompanyRole::from($role) : null;
    }

    public function isOwner(User $user): bool
    {
        return $this->roleFor($user) === CompanyRole::Owner;
    }

    /**
     * Whether this person may enter the workspace right now: they must have a
     * membership row, and that membership must either be the Owner's or have
     * access enabled. The Owner arm is enforced here rather than trusted from
     * the column, so no data state can lock a workspace's own Owner out.
     *
     * Read straight from the pivot table, like {@see roleFor()}, because callers
     * run this right after an access change.
     */
    public function hasWorkspaceAccess(User $user): bool
    {
        $membership = DB::table('company_user')
            ->where('company_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->first(['role', 'access_disabled_at']);

        if ($membership === null) {
            return false;
        }

        return $membership->role === CompanyRole::Owner->value
            || $membership->access_disabled_at === null;
    }

    /**
     * When this member's workspace access was disabled, or null while it is
     * enabled. The Team area reads this to state the member's access in plain
     * language; authorization reads {@see hasWorkspaceAccess()}.
     */
    public function workspaceAccessDisabledAt(User $user): ?Carbon
    {
        if ($this->hasWorkspaceAccess($user)) {
            return null;
        }

        $disabledAt = DB::table('company_user')
            ->where('company_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->value('access_disabled_at');

        return is_string($disabledAt) ? Carbon::parse($disabledAt) : null;
    }

    /** @return HasMany<CompanyInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    /** @return HasMany<Candidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** @return HasMany<Job, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /** @return HasMany<Referral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /** @return HasMany<Pipeline, $this> */
    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    /** @return HasOne<Pipeline, $this> */
    public function defaultPipeline(): HasOne
    {
        return $this->hasOne(Pipeline::class)->where('is_default', true);
    }

    /**
     * Every status in the company, across all of its pipelines. Statuses belong to
     * a pipeline — this exists for tenant-wide bookkeeping only, never to resolve
     * the workflow of a job.
     *
     * @return HasMany<Status, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<Interview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /** @return HasMany<ApplicationAnswer, $this> */
    public function applicationAnswers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class);
    }

    /** @return HasMany<ApplicationDocument, $this> */
    public function applicationDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /** @return HasMany<JobCriterion, $this> */
    public function jobCriteria(): HasMany
    {
        return $this->hasMany(JobCriterion::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasOne<CompanyAiSetting, $this> */
    public function aiSetting(): HasOne
    {
        return $this->hasOne(CompanyAiSetting::class);
    }

    /** @return HasMany<CompanyEmailProviderSetting, $this> */
    public function emailProviderSettings(): HasMany
    {
        return $this->hasMany(CompanyEmailProviderSetting::class);
    }

    /** @return HasOne<CompanyEmailProviderSetting, $this> */
    public function defaultEmailProviderSetting(): HasOne
    {
        return $this->hasOne(CompanyEmailProviderSetting::class)->where('is_default', true);
    }

    /** @return HasMany<CompanyEmailNotificationSetting, $this> */
    public function emailNotificationSettings(): HasMany
    {
        return $this->hasMany(CompanyEmailNotificationSetting::class);
    }

    /** @return HasMany<ConnectedIntegration, $this> */
    public function connectedIntegrations(): HasMany
    {
        return $this->hasMany(ConnectedIntegration::class);
    }

    /** @return HasMany<AiUsageRecord, $this> */
    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    /** @return HasMany<PlanChange, $this> */
    public function planChanges(): HasMany
    {
        return $this->hasMany(PlanChange::class);
    }

    /** @return HasMany<CompanyAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(CompanyAuditLog::class);
    }

    public function hasFeature(Feature $feature): bool
    {
        return $this->plan->hasFeature($feature);
    }

    public function hasReachedLimit(Limit $limit, int $currentCount): bool
    {
        $max = $this->plan->getLimit($limit);

        return $max !== null && $currentCount >= $max;
    }

    public function getLimit(Limit $limit): ?int
    {
        return $this->plan->getLimit($limit);
    }
}
