<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/locale/{locale}', LocaleController::class)
    ->middleware(['web', 'auth'])
    ->name('locale.switch');
