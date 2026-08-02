<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\Limit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'plan_id'])]
class Company extends Model
{
    use HasFactory;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** @return HasMany<Job, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function automationEvents(): HasMany
    {
        return $this->hasMany(AutomationEvent::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

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

    /** @return HasMany<AiUsageRecord, $this> */
    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    public function planChanges(): HasMany
    {
        return $this->hasMany(PlanChange::class);
    }

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
