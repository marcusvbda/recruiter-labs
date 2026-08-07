<?php

namespace App\Providers;

use App\Jobs\AnalyzeApplicationFit;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\Job;
use App\Services\CompanyTopbarSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CompanyTopbarSummary::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if ($this->app->runningInConsole()) {
            // The default `composer run dev` queue listener only watches the
            // connection's default queue, so AI job queues (criteria
            // extraction and application analysis) need their own listener
            // to actually run locally.
            DevCommands::artisan(
                'queue:listen --queue='.AnalyzeJobCriteria::QUEUE.','.AnalyzeApplicationFit::QUEUE.' --tries=1 --timeout=0',
                'ai-queue',
            );
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Constrains Filament's {tenant} route parameter to the same shape
        // company slugs are validated against, so a malformed slug can never
        // reach tenant resolution in the first place.
        Route::pattern('tenant', '[a-z0-9]+(-[a-z0-9]+)*');

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        // Stores a short stable alias in polymorphic `*_type` columns
        // (e.g. `automatable_type`) instead of the FQCN.
        Relation::enforceMorphMap([
            'job' => Job::class,
        ]);
    }
}
