<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the signed-in user's preferred language for server-rendered text. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        app()->setLocale(in_array($locale, ['en', 'fil'], true) ? $locale : config('app.fallback_locale'));

        return $next($request);
    }
}
