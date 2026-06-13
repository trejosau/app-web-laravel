<?php

namespace App\Http\Middleware;

use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountReauthenticated
{
    public const SESSION_KEY = 'account_reauthenticated_at';

    public function __construct(private readonly SecurityAuditService $auditService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $verifiedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($verifiedAt >= now()->subMinutes(10)->timestamp) {
            return $next($request);
        }

        $request->session()->forget(self::SESSION_KEY);
        $this->auditService->log($request, 'access.denied', 'warning', 403, $request->user()?->id, [
            'reason' => 'account_reauth_required',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'code' => config('security_errors.account.reauth_required.code'),
                'message' => config('security_errors.account.reauth_required.userInfo'),
            ], 403);
        }

        return redirect()->guest(route('account.reauth'));
    }
}
