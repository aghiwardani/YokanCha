<?php

namespace App\Providers;

use App\Services\CartService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dates shown to customers use Indonesian month names (Des, Agu, ...).
        \Illuminate\Support\Carbon::setLocale('id');

        // The customer header shows "Keranjang · N" on every page.
        View::composer('layouts.app', function ($view) {
            $view->with('cartCount', app(CartService::class)->count());
        });
    }
}
