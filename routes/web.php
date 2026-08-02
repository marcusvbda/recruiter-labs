<?php

use App\Http\Controllers\ApplicationDocumentController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReferralController;
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

Route::get('/locale/{locale}', LocaleController::class)
    ->middleware(['web', 'auth'])
    ->name('locale.switch');

Route::prefix('admin/{company:slug}/applications/{application}/documents/{document}')
    ->middleware(Authenticate::class)
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/view', [ApplicationDocumentController::class, 'show'])
            ->name('application-documents.view');
        Route::get('/download', [ApplicationDocumentController::class, 'download'])
            ->name('application-documents.download');
    });
