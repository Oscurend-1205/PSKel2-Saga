<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderHistoryController;
use Illuminate\Support\Facades\Route;

// Shopee Home / Product Catalog
Route::get('/', [CheckoutController::class, 'catalog'])->name('catalog.index');

// Guest Name Login (Simple Audience Authentication)
Route::post('/guest-login', function (\Illuminate\Http\Request $request) {
    $request->validate(['name' => 'required|string|min:2|max:50']);
    $name = trim($request->input('name'));
    session(['audience_name' => $name]);
    return back()->with('success', "Halo {$name}, selamat datang di Shambat!");
})->name('guest.login');

Route::get('/guest-logout', function () {
    session()->forget('audience_name');
    return back()->with('info', 'Nama audiens berhasil di-reset.');
})->name('guest.logout');

// Shopping Cart Routes (Shopee Style)
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::post('/update', [CartController::class, 'update'])->name('update');
    Route::post('/remove', [CartController::class, 'remove'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
});

// Checkout & Saga Order Flow
Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'checkout'])->name('store');
    Route::get('/pay/{orderCode}', [CheckoutController::class, 'pay'])->name('pay');
    Route::post('/simulate-pay/{orderCode}', [CheckoutController::class, 'simulatePayment'])->name('simulate_pay');
    Route::get('/status/{orderCode}', [CheckoutController::class, 'status'])->name('status');
});

// Riwayat Pesanan milik audiens (guest session)
Route::get('/orders', [OrderHistoryController::class, 'index'])->name('orders.index');
Route::get('/orders/{orderCode}', [OrderHistoryController::class, 'show'])->name('orders.show');

// Midtrans Webhook (CSRF excluded in bootstrap/app.php)
Route::post('/midtrans/webhook', [CheckoutController::class, 'midtransWebhook'])->name('midtrans.webhook');

// Presentasi — rute mandiri tanpa ketergantungan Service Provider
Route::get('/presentasi', function () {
    $file = resource_path('saga-bridge/presentasi.html');
    if (! is_file($file)) {
        return response('File presentasi tidak ditemukan di resources/saga-bridge/presentasi.html', 404);
    }
    return response()->file($file, [
        'Content-Type'  => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-store',
    ]);
})->name('presentasi');

// Saga Bridge API (live demo dari presentasi)
Route::prefix('saga-bridge/api')
    ->middleware(\App\SagaBridge\Http\BridgeGuard::class)
    ->name('saga-bridge.')
    ->group(function () {
        Route::get('ping',          [\App\SagaBridge\Http\BridgeController::class, 'ping'])->name('ping');
        Route::get('feed',          [\App\SagaBridge\Http\BridgeController::class, 'feed'])->name('feed');
        Route::post('scenario',     [\App\SagaBridge\Http\BridgeController::class, 'scenario'])->name('scenario');
        Route::post('step',         [\App\SagaBridge\Http\BridgeController::class, 'step'])->name('step');
        Route::post('flush',        [\App\SagaBridge\Http\BridgeController::class, 'flush'])->name('flush');
        Route::post('release',      [\App\SagaBridge\Http\BridgeController::class, 'release'])->name('release');
        Route::post('reset',        [\App\SagaBridge\Http\BridgeController::class, 'reset'])->name('reset');
        Route::options('{any}',     [\App\SagaBridge\Http\BridgeController::class, 'preflight'])->where('any', '.*')->name('preflight');
    });

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::post('/outbox/process', [AdminController::class, 'processOutbox'])->name('outbox.process');
    Route::post('/reset', [AdminController::class, 'resetDemo'])->name('reset');
});
