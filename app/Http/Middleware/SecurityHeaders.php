<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const OCR_WORKER_SOURCE = 'https://cdn.jsdelivr.net/npm/tesseract.js@v7.0.0/dist/worker.min.js';

    private const OCR_CORE_SOURCE = 'https://cdn.jsdelivr.net/npm/tesseract.js-core@v7.0.0/';

    private const OCR_LANGUAGE_SOURCE = 'https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng/4.0.0_best_int/';

    private const BASIC_CSP = "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        $usesFirebase = $request->is('notify/*', 'firebase-messaging-sw.js');
        $usesLivewire = $request->is(
            'login',
            'register',
            'forgot-password',
            'reset-password/*',
            'verify-email',
            'verify-email/*',
            'confirm-password',
            'dashboard',
            'admin',
            'admin/*',
            'settings',
            'settings/*',
        );
        $strictCsp = $this->strictCsp($nonce, $request->is('admin'), $usesFirebase, $usesLivewire);
        $response->headers->set(
            'Content-Security-Policy',
            app()->isProduction() || config('app.strict_csp') ? $strictCsp : self::BASIC_CSP
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

    private function strictCsp(string $nonce, bool $allowsInvoiceOcr, bool $allowsFirebase, bool $allowsLivewire): string
    {
        $scriptSources = "'self' 'nonce-{$nonce}'";
        $connectSources = "'self'";
        $workerSources = "'self'";
        $imageSources = "'self' data: https://api.qrserver.com";

        if ($allowsLivewire) {
            $scriptSources .= " 'unsafe-eval'";
        }

        if ($allowsFirebase) {
            $scriptSources .= ' https://www.gstatic.com';
            $connectSources .= ' https://firebaseinstallations.googleapis.com https://fcmregistrations.googleapis.com';
        }

        if ($allowsInvoiceOcr) {
            $scriptSources .= ' '.self::OCR_WORKER_SOURCE.' '.self::OCR_CORE_SOURCE;
            $connectSources .= ' '.self::OCR_WORKER_SOURCE.' '.self::OCR_CORE_SOURCE.' '.self::OCR_LANGUAGE_SOURCE;
            $workerSources .= ' blob: '.self::OCR_WORKER_SOURCE;
            $imageSources .= ' blob:';
        }

        return "default-src 'self'; "
            ."script-src {$scriptSources}; "
            ."script-src-attr 'none'; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
            ."font-src 'self' https://fonts.bunny.net; "
            ."img-src {$imageSources}; "
            ."connect-src {$connectSources}; "
            ."worker-src {$workerSources}; "
            ."manifest-src 'self'; media-src 'self'; frame-src 'none'; object-src 'none'; "
            ."base-uri 'self'; form-action 'self'; frame-ancestors 'self'; upgrade-insecure-requests";
    }
}
