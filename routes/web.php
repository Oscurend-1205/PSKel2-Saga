<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

// Shopee Home / Product Catalog
Route::get('/', [CheckoutController::class, 'catalog'])->name('catalog.index');

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

// Midtrans Webhook (CSRF excluded in bootstrap/app.php)
Route::post('/midtrans/webhook', [CheckoutController::class, 'midtransWebhook'])->name('midtrans.webhook');

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::post('/outbox/process', [AdminController::class, 'processOutbox'])->name('outbox.process');
    Route::post('/reset', [AdminController::class, 'resetDemo'])->name('reset');
});
