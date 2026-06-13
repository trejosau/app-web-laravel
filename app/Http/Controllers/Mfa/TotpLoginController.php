<?php

namespace App\Http\Controllers\Mfa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mfa\TotpCodeRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use App\Services\TotpService;
use App\Services\UserSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TotpLoginController extends Controller
{
    public function show(Request $request, MfaPendingSessionService $mfaPendingSession): View|RedirectResponse
    {
        $user = $mfaPendingSession->pendingUser($request);
        abort_unless($user !== null, 404);

        if ($user->totp_enabled_at === null || empty($user->totp_secret)) {
            return redirect()->route('totp.setup');
        }

        return view('auth.totp-login');
    }

    public function verify(
        TotpCodeRequest $request,
        TotpService $totpService,
        MfaPendingSessionService $mfaPendingSession,
        SecurityAuditService $auditService,
        UserSessionService $sessions
    ): RedirectResponse {
        $user = $mfaPendingSession->pendingUser($request);
        abort_unless($user !== null, 404);

        $usedCounter = empty($user->totp_secret)
            ? null
            : $totpService->verify($user->totp_secret, $request->validated('otp'), $user->totp_last_used_counter);

        if ($usedCounter === null) {
            $auditService->log($request, 'totp.failed', 'warning', 422, $user->id);

            return back()->withErrors(['otp' => config('security_errors.mfa.invalid_totp.code').': '.config('security_errors.mfa.invalid_totp.userInfo')]);
        }

        $user->forceFill([
            'totp_last_used_counter' => $usedCounter,
        ])->save();

        $auditService->log($request, 'totp.verified', 'info', 200, $user->id);

        return $this->completeAfterTotp($request, $user, $mfaPendingSession, $auditService, $sessions);
    }

    /**
     * Complete login after TOTP when no additional factor is required.
     */
    public function completeAfterTotp(
        Request $request,
        User $user,
        MfaPendingSessionService $mfaPendingSession,
        SecurityAuditService $auditService,
        UserSessionService $sessions
    ): RedirectResponse {
        $level = $mfaPendingSession->pendingLevel($request);

        if ($level >= 3 || $user->role?->name === Role::ADMIN) {
            $mfaPendingSession->markTotpVerified($request);

            return $user->webauthn_enabled_at && $user->webauthnCredentials()->exists()
                ? redirect()->route('webauthn.login')
                : redirect()->route('webauthn.setup');
        }

        $mfaPendingSession->clear($request);
        $sessionTrace = $sessions->completeLogin($user, $request, 2);

        $auditService->log($request, 'login.success', 'info', 200, $user->id, $sessionTrace + [
            'mfa_level' => 2,
            'role' => $user->role?->name,
        ]);

        return redirect()->route('dashboard.user')->with('status', 'Sesion iniciada.');
    }
}
