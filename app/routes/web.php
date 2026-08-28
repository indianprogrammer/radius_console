<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NasController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::prefix('subscribers')->name('subscribers.')->group(function () {
    Route::get('/', [SubscriberController::class, 'index'])->name('index');
    Route::get('/create', [SubscriberController::class, 'create'])->name('create');
    Route::post('/', [SubscriberController::class, 'store'])->name('store');
});

Route::prefix('nas')->name('nas.')->group(function () {
    Route::get('/', [NasController::class, 'index'])->name('index');
    Route::get('/create', [NasController::class, 'create'])->name('create');
    Route::post('/', [NasController::class, 'store'])->name('store');
    Route::get('/{nas}/edit', [NasController::class, 'edit'])->name('edit');
    Route::put('/{nas}', [NasController::class, 'update'])->name('update');
    Route::delete('/{nas}', [NasController::class, 'destroy'])->name('destroy');
});

Route::prefix('plans')->name('plans.')->group(function () {
    Route::get('/', [PlanController::class, 'index'])->name('index');
    Route::get('/create', [PlanController::class, 'create'])->name('create');
    Route::post('/', [PlanController::class, 'store'])->name('store');
});

// Per-user theme preference persistence (best-effort, SRD §3.2).
Route::post('/profile/theme', function (\Illuminate\Http\Request $request) {
    $theme = $request->input('theme');
    if (auth()->check() && in_array($theme, ['light', 'dark'])) {
        auth()->user()->update(['theme_pref' => $theme]);
    }
    return response()->noContent();
});
