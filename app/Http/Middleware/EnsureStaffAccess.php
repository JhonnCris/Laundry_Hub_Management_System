<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks self-registered accounts until an admin approves them
 * (role "pending"). Only staff and admin may use the staff API.
 */
class EnsureStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->user()?->role, ['staff', 'admin'], true), 403, 'Your account is awaiting admin approval.');

        return $next($request);
    }
}
