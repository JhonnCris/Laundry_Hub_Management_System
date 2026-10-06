<?php

use App\Http\Middleware\EnsureStaffAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Vercel terminates TLS in front of PHP: trust its forwarded headers.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
        ]);
        $middleware->alias([
            'staff.access' => EnsureStaffAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Vercel's filesystem is read-only except /tmp: keep Laravel's writable folders there.
if (getenv('VERCEL')) {
    $storage = '/tmp/storage';
    foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs'] as $dir) {
        if (! is_dir("{$storage}/{$dir}")) {
            mkdir("{$storage}/{$dir}", 0777, true);
        }
    }
    $app->useStoragePath($storage);
}

return $app;
