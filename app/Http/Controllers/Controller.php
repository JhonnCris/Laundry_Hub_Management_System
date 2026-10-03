<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Record who did a sensitive action (never pass passwords or tokens).
     *
     * @param  array<string, mixed>  $context
     */
    protected function audit(string $action, array $context = []): void
    {
        Log::channel('audit')->info($action, [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'ip' => request()->ip(),
            ...$context,
        ]);
    }
}
