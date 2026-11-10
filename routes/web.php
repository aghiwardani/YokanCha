<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu.index');

Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::delete('/cart/{lineKey}', [CartController::class, 'remove'])
    ->where('lineKey', '.*')
    ->name('cart.remove');
