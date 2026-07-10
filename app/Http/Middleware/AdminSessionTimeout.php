<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionTimeout
{
    public function __construct(
        private readonly MfaPendingSessionService $mfaPendingSession,
        private readonly SecurityAuditService $auditService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role?->name !== Role::ADMIN) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get('admin_last_activity_at', now()->timestamp);

        if ($lastActivity < now()->subMinutes(15)->timestamp) {
            $userId = $user->id;
            Auth::logout();
            $this->mfaPendingSession->clear($request);
            $request->session()->forget(['admin_reauthenticated_at', 'auth_completed_mfa_level', 'admin_last_activity_at']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->auditService->log($request, 'session.expired', 'warning', 440, $userId, [
                'reason' => 'admin_idle_timeout',
            ]);

            return redirect()->route('login')->withErrors([
                'username' => config('security_errors.auth.session_expired.code').': '.config('security_errors.auth.session_expired.userInfo'),
            ]);
        }

        $request->session()->put('admin_last_activity_at', now()->timestamp);

        return $next($request);
    }
}
