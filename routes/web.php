<?php

use App\Http\Controllers\ApplicationDocumentController;
use App\Http\Controllers\CandidateImportReportController;
use App\Http\Controllers\CandidateImportTemplateController;
use App\Http\Controllers\CandidateMaterialController;
use App\Http\Controllers\CareerJobApplicationController;
use App\Http\Controllers\CareersController;
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

Route::get('/careers/{company:slug}', [CareersController::class, 'show'])
    ->name('careers.show');

Route::get('/careers/{company:slug}/jobs/{key}', [CareersController::class, 'job'])
    ->whereUuid('key')
    ->name('careers.jobs.show');

Route::post('/careers/{company:slug}/jobs/{key}', [CareerJobApplicationController::class, 'store'])
    ->whereUuid('key')
    ->middleware('throttle:30,1')
    ->name('careers.jobs.apply.store');

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

Route::get('/admin/candidate-imports/template', [CandidateImportTemplateController::class, 'download'])
    ->middleware(Authenticate::class)
    ->name('candidate-import-batches.template');

Route::prefix('admin/{company:slug}/candidates/{candidate}/materials/{material}')
    ->middleware([Authenticate::class, 'verified'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/view', [CandidateMaterialController::class, 'show'])->name('candidate-materials.view');
        Route::get('/download', [CandidateMaterialController::class, 'download'])->name('candidate-materials.download');
    });

// Both files carry candidate detail, so they are served like a CV rather than
// linked: private disk, attachment only, workspace checked per request.
Route::prefix('admin/{company:slug}/candidate-imports/{candidateImportBatch}')
    ->middleware([Authenticate::class, 'verified'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/report', [CandidateImportReportController::class, 'report'])->name('candidate-import-batches.report');
        Route::get('/correction', [CandidateImportReportController::class, 'correction'])->name('candidate-import-batches.correction');
    });
