<?php

use App\SagaBridge\Http\BridgeController;
use App\SagaBridge\Http\BridgeGuard;
use Illuminate\Support\Facades\Route;

// File presentasi yang dilayani langsung oleh Laravel (satu origin, tanpa masalah CORS/cookie).
Route::get('presentasi', [BridgeController::class, 'presentation'])->name('saga-bridge.presentation');

Route::prefix('saga-bridge/api')
    ->middleware(BridgeGuard::class)
    ->name('saga-bridge.')
    ->group(function () {
        Route::get('ping', [BridgeController::class, 'ping'])->name('ping');
        Route::get('feed', [BridgeController::class, 'feed'])->name('feed');
        Route::post('scenario', [BridgeController::class, 'scenario'])->name('scenario');
        Route::post('step', [BridgeController::class, 'step'])->name('step');
        Route::post('flush', [BridgeController::class, 'flush'])->name('flush');
        Route::post('release', [BridgeController::class, 'release'])->name('release');
        Route::post('reset', [BridgeController::class, 'reset'])->name('reset');
        Route::options('{any}', [BridgeController::class, 'preflight'])->where('any', '.*')->name('preflight');
    });
