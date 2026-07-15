<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminReauthenticated;
use App\Http\Requests\Admin\AdminReauthRequest;
use App\Services\SecurityAuditService;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class ReauthController extends Controller
{
    /**
     * Display the requested resource or form.
     */
    public function show(): View
    {
        return view('admin.reauth');
    }

    /**
     * Validate and process the submitted request.
     */
    public function store(
        AdminReauthRequest $request,
        TotpService $totpService,
        SecurityAuditService $auditService
    ): RedirectResponse|JsonResponse {
        $user = $request->user();

        $validPassword = Hash::check($request->validated('password'), $user->password);
        $usedCounter = filled($user->totp_secret)
            ? $totpService->verify($user->totp_secret, $request->validated('otp'), $user->totp_last_used_counter)
            : null;
        $validTotp = $usedCounter !== null;

        if (! $validPassword || ! $validTotp) {
            $auditService->log($request, 'login.failed', 'warning', 422, $user->id, [
                'reason' => 'admin_reauth_failed',
            ]);

            $message = config('security_errors.rbac.admin_reauth_failed.code').': '.config('security_errors.rbac.admin_reauth_failed.userInfo');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['password' => [$message]],
                ], 422);
            }

            return back()->withErrors(['password' => $message]);
        }

        $user->forceFill([
            'totp_last_used_counter' => $usedCounter,
        ])->save();

        $reauthenticatedAt = now();
        $request->session()->put(EnsureAdminReauthenticated::SESSION_KEY, $reauthenticatedAt->timestamp);
        $auditService->log($request, 'admin.reauthenticated', 'info', 200, $user->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Identidad confirmada. La acción continuará automáticamente.',
                'reauthenticated_until' => $reauthenticatedAt
                    ->copy()
                    ->addMinutes(EnsureAdminReauthenticated::TIMEOUT_MINUTES)
                    ->timestamp,
                'reauthenticated_for' => EnsureAdminReauthenticated::TIMEOUT_MINUTES * 60,
            ]);
        }

        return redirect()->intended(route('admin.users.index'))->with('status', 'Reautenticacion completada.');
    }
}
