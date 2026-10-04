<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            // Never show stack traces or config on a live site, even if APP_DEBUG is set by mistake.
            config(['app.debug' => false]);
        }

        // Who signed in, and who is failing to: kept in the audit log (never the password).
        if (! $this->app->runningUnitTests()) {
            Event::listen(Login::class, fn (Login $event) => Log::channel('audit')->info('auth.login', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
            ]));
            Event::listen(Failed::class, fn (Failed $event) => Log::channel('audit')->warning('auth.failed', [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]));
            Event::listen(Lockout::class, fn () => Log::channel('audit')->warning('auth.lockout', ['ip' => request()->ip()]));
        }

        Gate::define('admin', fn (User $user) => $user->role === 'admin');

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->mixedCase()->numbers()->uncompromised()
            : Password::min(8));
    }
}
