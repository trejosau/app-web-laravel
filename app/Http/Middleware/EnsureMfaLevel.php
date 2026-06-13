<?php

namespace App\Http\Middleware;

use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaLevel
{
    public function __construct(private readonly SecurityAuditService $auditService) {}

    public function handle(Request $request, Closure $next, int $level): Response
    {
        $user = $request->user();
        $actualLevel = (int) $request->session()->get('auth_completed_mfa_level', 0);

        if ($user === null || $actualLevel < $level) {
            $this->auditService->log($request, 'access.denied', 'warning', 403, $user?->id, [
                'required_mfa_level' => $level,
            ]);

            abort(403);
        }

        return $next($request);
    }
}
