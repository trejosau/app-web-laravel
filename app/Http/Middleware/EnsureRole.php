<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function __construct(private readonly SecurityAuditService $auditService) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $roleName = $user?->role?->name;
        $allowed = in_array($roleName, $roles, true)
            || ($roleName === Role::LEGACY_GUESS && in_array(Role::GUEST, $roles, true));

        if ($user === null || ! $allowed) {
            $this->auditService->log($request, 'access.denied', 'warning', 403, $user?->id, [
                'required_roles' => $roles,
            ]);

            abort(403);
        }

        return $next($request);
    }
}
