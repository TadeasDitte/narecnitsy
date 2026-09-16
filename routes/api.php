<?php

use App\Http\Controllers\Api\CandleController;
use App\Http\Controllers\Api\MarketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/markets', [MarketController::class, 'index']);
    Route::get('/candles', [CandleController::class, 'index']);
});
