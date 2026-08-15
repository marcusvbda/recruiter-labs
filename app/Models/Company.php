<?php

namespace App\Models;

use App\Actions\ProvisionDefaultPipeline;
use App\Enums\Feature;
use App\Enums\Limit;
use Database\Factories\CompanyFactory;
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
        return $this->belongsToMany(User::class)->withTimestamps();
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

    /** @return HasOne<CompanyScoringSetting, $this> */
    public function scoringSetting(): HasOne
    {
        return $this->hasOne(CompanyScoringSetting::class);
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
