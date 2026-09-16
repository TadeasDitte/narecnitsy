<?php

use App\Http\Controllers\ApiAccessController;
use App\Http\Controllers\MarketDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// This is a purpose-built internal tool, not a public product - skip the
// generic starter-kit splash page and go straight to the app (or login).
Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route('market-data.index')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('market-data', [MarketDataController::class, 'index'])->name('market-data.index');
    Route::post('market-data/backfill', [MarketDataController::class, 'backfill'])->name('market-data.backfill');

    Route::get('api-access', [ApiAccessController::class, 'index'])->name('api-access.index');
    Route::post('api-access/tokens', [ApiAccessController::class, 'store'])->name('api-access.tokens.store');
    Route::delete('api-access/tokens/{token}', [ApiAccessController::class, 'destroy'])->name('api-access.tokens.destroy');
});

require __DIR__.'/settings.php';
