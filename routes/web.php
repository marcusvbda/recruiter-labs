<?php

use App\Http\Controllers\JobController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReferralController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'))->name('home');

Route::get('/job/{key}', [JobController::class, 'show'])->name('job.show');

Route::get('/job/{key}/preview', [JobController::class, 'preview'])
    ->middleware(Authenticate::class)
    ->name('job.preview');

Route::get('/referal/{key}', [ReferralController::class, 'show'])->name('referral.show');

Route::get('/locale/{locale}', LocaleController::class)
    ->middleware(['web', 'auth'])
    ->name('locale.switch');
