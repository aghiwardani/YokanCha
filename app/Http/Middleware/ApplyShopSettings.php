<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop settings saved in the admin override .env for every web request.
 */
class ApplyShopSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            Setting::apply();
        } catch (\Illuminate\Database\QueryException) {
            // Table not migrated yet: keep the .env values.
        }

        return $next($request);
    }
}
