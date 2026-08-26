<?php

use App\Http\Controllers\ApplicationDocumentController;
use App\Http\Controllers\ConnectedIntegrationOAuthController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'))->name('home');

Route::get('/job/{key}', [JobController::class, 'show'])->name('job.show');

Route::post('/job/{key}/apply', [JobApplicationController::class, 'store'])
    ->whereUuid('key')
    ->middleware('throttle:30,1')
    ->name('job.apply.store');

Route::get('/job/{key}/preview', [JobController::class, 'preview'])
    ->middleware(Authenticate::class)
    ->name('job.preview');

Route::get('/referal/{key}', [ReferralController::class, 'show'])->name('referral.show');

// Reachable by guests and by signed-in but unverified accounts: opening an
// invitation is how those two states are explained in the first place.
Route::get('/invitations/{token}', [WorkspaceInvitationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware(['throttle:30,1', SetLocale::class])
    ->name('workspace-invitations.show');

Route::post('/invitations/{token}', [WorkspaceInvitationController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware([Authenticate::class, 'throttle:30,1', SetLocale::class])
    ->name('workspace-invitations.accept');

Route::get('/locale/{locale}', LocaleController::class)
    ->middleware(['web', 'auth'])
    ->name('locale.switch');

Route::middleware([Authenticate::class, 'verified', SetLocale::class])->group(function (): void {
    Route::get('/admin/{company:slug}/integrations/{plugin}/connect', [ConnectedIntegrationOAuthController::class, 'connect'])
        ->where('plugin', '[a-z0-9-]+')
        ->name('integrations.oauth.connect');
    Route::get('/admin/{company:slug}/integrations/{plugin}/reconnect', [ConnectedIntegrationOAuthController::class, 'reconnect'])
        ->where('plugin', '[a-z0-9-]+')
        ->name('integrations.oauth.reconnect');
    Route::delete('/admin/{company:slug}/integrations/{plugin}', [ConnectedIntegrationOAuthController::class, 'disconnect'])
        ->where('plugin', '[a-z0-9-]+')
        ->name('integrations.oauth.disconnect');
    Route::get('/integrations/oauth/callback', [ConnectedIntegrationOAuthController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('integrations.oauth.callback');
});

Route::prefix('admin/{company:slug}/applications/{application}/documents/{document}')
    ->middleware(Authenticate::class)
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/view', [ApplicationDocumentController::class, 'show'])
            ->name('application-documents.view');
        Route::get('/download', [ApplicationDocumentController::class, 'download'])
            ->name('application-documents.download');
    });
