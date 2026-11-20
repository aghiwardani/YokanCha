<?php

namespace App\Providers;

use App\Services\CartService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Rate limits against guessing and spam. Admin login is limited
        // inside AdminLoginController so it can show a friendly message.
        // Buyers are office colleagues who often share one public IP, so each
        // visitor (session) gets the tight limit and the IP a looser one.
        $perVisitor = fn (string $name, int $visitor, int $ip) => fn (Request $request) => [
            Limit::perMinute($visitor)->by($name.':s:'.$request->session()->getId()),
            Limit::perMinute($ip)->by($name.':ip:'.$request->ip()),
        ];
        RateLimiter::for('order-lookup', $perVisitor('order-lookup', 10, 60));
        RateLimiter::for('checkout', $perVisitor('checkout', 10, 60));
        RateLimiter::for('cart', $perVisitor('cart', 60, 300));

        // The customer header shows "Keranjang · N" on every page.
        View::composer('layouts.app', function ($view) {
            $view->with('cartCount', app(CartService::class)->count());
        });
    }
}
