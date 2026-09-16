<?php

use App\Http\Controllers\ApiAccessController;
use App\Http\Controllers\MarketDataController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('market-data', [MarketDataController::class, 'index'])->name('market-data.index');

    Route::get('api-access', [ApiAccessController::class, 'index'])->name('api-access.index');
    Route::post('api-access/tokens', [ApiAccessController::class, 'store'])->name('api-access.tokens.store');
    Route::delete('api-access/tokens/{token}', [ApiAccessController::class, 'destroy'])->name('api-access.tokens.destroy');
});

require __DIR__.'/settings.php';
