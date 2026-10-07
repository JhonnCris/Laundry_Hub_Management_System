<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Record who did a sensitive action (never pass passwords or tokens).
     * Saved to the audit_logs table (shown on the admin Activity Log) and the audit log file.
     *
     * @param  array<string, mixed>  $context
     */
    protected function audit(string $action, array $context = []): void
    {
        $who = ['user_id' => Auth::id(), 'user_email' => Auth::user()?->email, 'ip' => request()->ip()];

        AuditLog::query()->create([...$who, 'action' => $action, 'context' => $context, 'created_at' => now()]);

        if (! app()->runningUnitTests()) {
            Log::channel('audit')->info($action, [...$who, ...$context]);
        }
    }
}
