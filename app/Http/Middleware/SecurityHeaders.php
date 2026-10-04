<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** Where the app is allowed to load code, styles, images and data from (production only). */
    private const STRICT_CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.gstatic.com; "
        ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
        ."font-src 'self' https://fonts.bunny.net data:; "
        ."img-src 'self' data: https://api.qrserver.com; "
        ."connect-src 'self'; "
        ."worker-src 'self'; frame-src 'none'; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /** Pages that talk to Firebase get the lighter policy so push sign-up keeps working. */
    private const BASIC_CSP = "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        $usesFirebase = $request->is('notify/*') || $request->is('firebase-messaging-sw.js');
        $response->headers->set(
            'Content-Security-Policy',
            (app()->isProduction() || config('app.strict_csp')) && ! $usesFirebase ? self::STRICT_CSP : self::BASIC_CSP
        );

        // Account data must never sit in a shared cache or the browser's back/forward cache.
        if ($request->is('ajax/*') || $request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
