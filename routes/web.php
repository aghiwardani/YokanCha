<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu.index');

Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::delete('/cart/{lineKey}', [CartController::class, 'remove'])
    ->where('lineKey', '.*')
    ->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/confirmation/{orderNumber}', [CheckoutController::class, 'confirmation'])
    ->name('checkout.confirmation');

Route::get('/orders/lookup', [OrderLookupController::class, 'show'])->name('orders.lookup');
Route::post('/orders/lookup', [OrderLookupController::class, 'search'])->name('orders.lookup.search');
