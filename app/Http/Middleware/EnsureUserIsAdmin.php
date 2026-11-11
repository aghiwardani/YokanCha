<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only users flagged is_admin may use the /admin panel. Any other logged-in
 * user (e.g. the seeded test user) gets 403.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
