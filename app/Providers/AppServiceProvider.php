<?php

namespace App\Providers;

use App\Contracts\OAuthIntegrationPlugin;
use App\Jobs\AnalyzeApplicationFit;
use App\Jobs\AnalyzeJobCriteria;
use App\Jobs\SendRecruitmentEmail;
use App\Jobs\SyncInterviewResponseJob;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\CompanyTopbarSummary;
use App\Services\ConnectedIntegrationRegistry;
use App\Services\GmailRecruitmentEmailSender;
use App\Services\RecruitmentEmailSenderRegistry;
use App\Services\ResendRecruitmentEmailSender;
use Carbon\CarbonImmutable;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Notifications\VerifyEmail;
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

        $this->app->bind(VerifyEmail::class, VerifyEmailNotification::class);
        $this->app->bind(ResetPassword::class, ResetPasswordNotification::class);

        $this->app->singleton(ConnectedIntegrationRegistry::class, function (): ConnectedIntegrationRegistry {
            /** @var list<class-string<OAuthIntegrationPlugin>> $pluginClasses */
            $pluginClasses = config('connected-integrations.plugins', []);

            return new ConnectedIntegrationRegistry(array_map(
                fn (string $pluginClass): OAuthIntegrationPlugin => $this->app->make($pluginClass),
                $pluginClasses,
            ));
        });

        $this->app->singleton(RecruitmentEmailSenderRegistry::class, fn (): RecruitmentEmailSenderRegistry => new RecruitmentEmailSenderRegistry([
            $this->app->make(ResendRecruitmentEmailSender::class),
            $this->app->make(GmailRecruitmentEmailSender::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if ($this->app->runningInConsole()) {
            // The default `composer run dev` queue listener only watches the
            // connection's default queue, so dedicated queues need their own
            // listeners to actually run locally.
            DevCommands::artisan(
                'queue:listen --queue='.AnalyzeJobCriteria::QUEUE.','.AnalyzeApplicationFit::QUEUE.' --tries=1 --timeout=0',
                'ai-queue',
            );
            DevCommands::artisan(
                'queue:listen --queue='.SendRecruitmentEmail::QUEUE.' --timeout=60',
                'recruitment-email-queue',
            );
            DevCommands::artisan(
                'queue:listen --queue='.SyncInterviewResponseJob::QUEUE.' --timeout=60',
                'interview-sync-queue',
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

    }
}
