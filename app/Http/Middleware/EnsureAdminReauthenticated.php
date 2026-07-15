<?php

namespace App\Http\Middleware;

use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminReauthenticated
{
    public const SESSION_KEY = 'admin_reauthenticated_at';

    public const TIMEOUT_MINUTES = 5;

    /**
     * Create the object with its required collaborators.
     */
    public function __construct(private readonly SecurityAuditService $auditService) {}

    /**
     * Handle the incoming HTTP request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $verifiedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($verifiedAt < now()->subMinutes(self::TIMEOUT_MINUTES)->timestamp) {
            $request->session()->forget(self::SESSION_KEY);
            $this->auditService->log($request, 'access.denied', 'warning', 403, $request->user()?->id, [
                'reason' => 'admin_reauth_required',
            ]);

            return redirect()->route('admin.reauth');
        }

        return $next($request);
    }
}
