<?php

namespace App\Http\Middleware;

use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminReauthenticated
{
    public function __construct(private readonly SecurityAuditService $auditService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $verifiedAt = (int) $request->session()->get('admin_reauthenticated_at', 0);

        if ($verifiedAt < now()->subMinutes(5)->timestamp) {
            $request->session()->forget('admin_reauthenticated_at');
            $this->auditService->log($request, 'access.denied', 'warning', 403, $request->user()?->id, [
                'reason' => 'admin_reauth_required',
            ]);

            return redirect()->route('admin.reauth');
        }

        return $next($request);
    }
}
