<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaIsNotPending
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
        if ($this->mfaPendingSession->isPending($request)) {
            $this->auditService->log(
                $request,
                'access.denied',
                'warning',
                403,
                $request->session()->get('auth_pending_user_id'),
                ['reason' => 'mfa_pending']
            );

            return redirect()->route('mfa.pending');
        }

        return $next($request);
    }
}
