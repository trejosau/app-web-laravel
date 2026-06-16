<?php

namespace App\Http\Controllers\Mfa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mfa\RecoveryCodeRequest;
use App\Models\RecoveryCode;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use App\Services\UserSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecoveryCodeLoginController extends Controller
{
    public function show(Request $request, MfaPendingSessionService $mfaPendingSession): View
    {
        abort_unless($mfaPendingSession->pendingUser($request) !== null, 404);

        return view('auth.recovery-code-login');
    }

    public function verify(
        RecoveryCodeRequest $request,
        MfaPendingSessionService $mfaPendingSession,
        SecurityAuditService $auditService,
        UserSessionService $sessions
    ): RedirectResponse {
        $user = $mfaPendingSession->pendingUser($request);
        abort_unless($user !== null, 404);

        $code = Str::upper(trim($request->validated('code')));
        $usedCode = $this->consumeCode($user, $code);

        if ($usedCode === null) {
            $auditService->log($request, 'recovery_code.failed', 'warning', 422, $user->id);

            return back()->withErrors(['code' => config('security_errors.mfa.recovery_code_invalid.code').': '.config('security_errors.mfa.recovery_code_invalid.userInfo')]);
        }

        $auditService->log($request, 'recovery_code.used', 'info', 200, $user->id);

        return $this->completeAfterRecoveryCode($request, $user, $mfaPendingSession, $auditService, $sessions);
    }

    private function consumeCode(User $user, string $code): ?RecoveryCode
    {
        return DB::transaction(function () use ($user, $code): ?RecoveryCode {
            $recoveryCodes = $user->recoveryCodes()
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            foreach ($recoveryCodes as $recoveryCode) {
                if (! $recoveryCode->matches($code)) {
                    continue;
                }

                $recoveryCode->forceFill(['used_at' => now()])->save();

                return $recoveryCode;
            }

            return null;
        });
    }

    private function completeAfterRecoveryCode(
        Request $request,
        User $user,
        MfaPendingSessionService $mfaPendingSession,
        SecurityAuditService $auditService,
        UserSessionService $sessions
    ): RedirectResponse {
        $level = $mfaPendingSession->pendingLevel($request);

        if ($level >= 3 || $user->role?->name === Role::ADMIN) {
            $mfaPendingSession->markTotpVerified($request);

            return redirect()->route('email-otp.show');
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
