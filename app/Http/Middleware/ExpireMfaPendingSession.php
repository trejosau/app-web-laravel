<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpireMfaPendingSession
{
    public function __construct(
        private readonly MfaPendingSessionService $mfaPendingSession,
        private readonly SecurityAuditService $auditService
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->mfaPendingSession->isPending($request) && $this->mfaPendingSession->isExpired($request)) {
            $userId = $request->session()->get('auth_pending_user_id');

            $this->mfaPendingSession->clear($request);
            $request->session()->forget(['admin_reauthenticated_at', 'auth_completed_mfa_level']);
            $this->auditService->log($request, 'session.expired', 'warning', 440, $userId);

            return redirect()->route('login')->withErrors([
                'username' => config('security_errors.auth.session_expired.code').': '.config('security_errors.auth.session_expired.userInfo'),
            ]);
        }

        return $next($request);
    }
}
