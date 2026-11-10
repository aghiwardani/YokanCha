<?php

use App\Http\Controllers\Admin\PoPeriodController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\AdminLoginController;
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

// Admin login (unauthenticated) — guards the group below.
Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'store'])->name('admin.login.store');

// Admin panel — all /admin/* routes live inside this group behind the auth
// middleware. Tasks 9–11 add their period/product/order routes here.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

    Route::get('/periods', [PoPeriodController::class, 'index'])->name('periods');
    Route::post('/periods', [PoPeriodController::class, 'store'])->name('periods.store');
    Route::post('/periods/{period}/open', [PoPeriodController::class, 'open'])->name('periods.open');
    Route::post('/periods/{period}/close', [PoPeriodController::class, 'close'])->name('periods.close');

    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/quota', [ProductController::class, 'setQuota'])->name('products.quota');
});
