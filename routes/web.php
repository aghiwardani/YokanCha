<?php

use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PoPeriodController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SizeController;
use App\Http\Controllers\Admin\SweetnessLevelController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu.index');

Route::post('/cart', [CartController::class, 'add'])->middleware('throttle:cart')->name('cart.add');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::patch('/cart/{lineKey}', [CartController::class, 'update'])
    ->middleware('throttle:cart')
    ->where('lineKey', '.*')
    ->name('cart.update');
Route::delete('/cart/{lineKey}', [CartController::class, 'remove'])
    ->middleware('throttle:cart')
    ->where('lineKey', '.*')
    ->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/confirmation/{orderNumber}', [CheckoutController::class, 'confirmation'])
    ->middleware('throttle:order-lookup')
    ->name('checkout.confirmation');

Route::get('/orders/lookup', [OrderLookupController::class, 'show'])->name('orders.lookup');
Route::post('/orders/lookup', [OrderLookupController::class, 'search'])->middleware('throttle:order-lookup')->name('orders.lookup.search');

// Admin login (unauthenticated) — guards the group below.
Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'store'])->name('admin.login.store');

// Admin panel — all /admin/* routes live inside this group behind the auth
// middleware. Tasks 9–11 add their period/product/order routes here.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('admin.periods'))->name('home');

    Route::get('/periods', [PoPeriodController::class, 'index'])->name('periods');
    Route::post('/periods', [PoPeriodController::class, 'store'])->name('periods.store');
    Route::post('/periods/{period}/open', [PoPeriodController::class, 'open'])->name('periods.open');
    Route::post('/periods/{period}/close', [PoPeriodController::class, 'close'])->name('periods.close');
    Route::get('/periods/{period}/stock', [PoPeriodController::class, 'stock'])->name('periods.stock');
    Route::put('/periods/{period}/stock', [PoPeriodController::class, 'updateStock'])->name('periods.stock.update');
    Route::get('/periods/{period}/production', [ProductionController::class, 'show'])->name('periods.production');
    Route::post('/periods/{period}/production/recipes', [ProductionController::class, 'refreshRecipes'])->name('periods.production.recipes');
    Route::post('/periods/{period}/production/prices', [ProductionController::class, 'refreshPrices'])->name('periods.production.prices');

    // Master data: products (Produk), ingredients (Bahan) and sizes (Ukuran) on one page.
    Route::get('/products', fn () => redirect()->to(route('admin.master').'#produk'))->name('products');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('/products/{product}/active', [ProductController::class, 'setActive'])->name('products.active');

    Route::get('/master', [MasterDataController::class, 'index'])->name('master');

    Route::get('/ingredients', fn () => redirect()->to(route('admin.master').'#bahan'))->name('ingredients');
    Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
    Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
    Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');

    Route::get('/sizes', fn () => redirect()->to(route('admin.master').'#ukuran'))->name('sizes');
    Route::post('/sizes', [SizeController::class, 'store'])->name('sizes.store');
    Route::put('/sizes/{size}', [SizeController::class, 'update'])->name('sizes.update');
    Route::delete('/sizes/{size}', [SizeController::class, 'destroy'])->name('sizes.destroy');

    Route::post('/sweetness', [SweetnessLevelController::class, 'store'])->name('sweetness.store');
    Route::put('/sweetness/{level}', [SweetnessLevelController::class, 'update'])->name('sweetness.update');
    Route::delete('/sweetness/{level}', [SweetnessLevelController::class, 'destroy'])->name('sweetness.destroy');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->name('orders.mark-paid');
    Route::post('/orders/{order}/mark-unpaid', [OrderController::class, 'markUnpaid'])->name('orders.mark-unpaid');
    Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('/settings/shop', [SettingsController::class, 'updateShop'])->name('settings.shop');
    Route::put('/settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account');
});
