<?php

namespace App\Http\Controllers\Mfa;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use App\Services\UserSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailOtpController extends Controller
{
    public function show(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        EmailOtpService $emailOtp,
        SecurityAuditService $auditService
    ): View {
        $user = $this->pendingUser($request, $mfaPendingSession);

        $emailOtp->send($user, $request, 'mfa');
        $auditService->log($request, 'email_otp.sent', 'info', 202, $user->id, [
            'purpose' => 'mfa',
        ]);

        return view('auth.email-otp-login', [
            'email' => $this->maskedEmail((string) $user->email),
        ]);
    }

    public function verify(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        EmailOtpService $emailOtp,
        SecurityAuditService $auditService,
        UserSessionService $sessions
    ): RedirectResponse {
        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $this->pendingUser($request, $mfaPendingSession);

        if (! $emailOtp->verify($request, (string) $request->input('otp'), 'mfa')) {
            $auditService->log($request, 'email_otp.failed', 'warning', 422, $user->id, [
                'purpose' => 'mfa',
            ]);

            return back()->withErrors(['otp' => 'OTP inválido o expirado.']);
        }

        $emailOtp->clear($request);
        $mfaPendingSession->clear($request);
        $sessionTrace = $sessions->completeLogin($user, $request, 3);

        $auditService->log($request, 'email_otp.verified', 'info', 200, $user->id);
        $auditService->log($request, 'login.success', 'info', 200, $user->id, $sessionTrace + [
            'mfa_level' => 3,
            'role' => $user->role?->name,
        ]);

        return redirect()->route('dashboard.admin')->with('status', 'Sesión iniciada.');
    }

    public function resend(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        EmailOtpService $emailOtp,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $this->pendingUser($request, $mfaPendingSession);

        $emailOtp->send($user, $request, 'mfa');
        $auditService->log($request, 'email_otp.sent', 'info', 202, $user->id, [
            'purpose' => 'mfa',
            'resend' => true,
        ]);

        return back()->with('status', 'OTP reenviado.');
    }

    private function pendingUser(Request $request, MfaPendingSessionService $mfaPendingSession): User
    {
        $user = $mfaPendingSession->pendingUser($request);

        abort_unless(
            $user !== null
            && $request->session()->has('auth_pending_totp_verified_at')
            && $mfaPendingSession->pendingLevel($request) >= 3,
            404
        );

        abort_unless(is_string($user->email) && trim($user->email) !== '', 403);

        return $user;
    }

    private function maskedEmail(string $email): string
    {
        return preg_replace('/(^.).*(@.*$)/', '$1***$2', $email) ?? $email;
    }
}
