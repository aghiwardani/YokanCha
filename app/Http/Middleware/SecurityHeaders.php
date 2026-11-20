<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every web response.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Not embeddable in another site's frame (clickjacking).
            'X-Frame-Options' => 'SAMEORIGIN',
            // Don't let the browser guess a file's type (e.g. run text as script).
            'X-Content-Type-Options' => 'nosniff',
            // Other sites only learn the domain we came from, not the full URL.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // The shop never needs these device features.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // Only over HTTPS: tell browsers to keep using HTTPS for a year.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
