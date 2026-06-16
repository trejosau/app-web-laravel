<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailOtpVerificationController extends Controller
{
    private const USER_KEY = 'registration_email_otp_user_id';

    public function show(Request $request): View
    {
        abort_unless($this->pendingUser($request) !== null, 404);

        return view('auth.email-otp-register');
    }

    public function verify(
        Request $request,
        EmailOtpService $emailOtp,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $this->pendingUser($request);
        abort_unless($user !== null, 404);

        if (! $emailOtp->verify($request, (string) $request->input('otp'), 'registration')) {
            $auditService->log($request, 'email_otp.failed', 'warning', 422, $user->id, [
                'purpose' => 'registration',
            ]);

            return back()->withErrors(['otp' => 'OTP inválido o expirado.']);
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $emailOtp->clear($request);
        $request->session()->forget(self::USER_KEY);

        $auditService->log($request, 'email.verified', 'info', 200, $user->id, [
            'method' => 'otp',
        ]);

        return redirect()->route('login')->with('status', 'Correo verificado. Ya puedes iniciar sesión.');
    }

    public function resend(
        Request $request,
        EmailOtpService $emailOtp,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $this->pendingUser($request);
        abort_unless($user !== null, 404);

        $emailOtp->send($user, $request, 'registration');
        $auditService->log($request, 'email_otp.sent', 'info', 202, $user->id, [
            'purpose' => 'registration',
        ]);

        return back()->with('status', 'OTP reenviado.');
    }

    public static function rememberPendingUser(Request $request, User $user): void
    {
        $request->session()->put(self::USER_KEY, $user->id);
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::USER_KEY);

        return $userId ? User::query()->find($userId) : null;
    }
}
