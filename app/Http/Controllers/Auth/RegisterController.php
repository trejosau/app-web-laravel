<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Services\EmailOtpService;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(
        RegisterRequest $request,
        AuthService $authService,
        EmailOtpService $emailOtp,
        SecurityAuditService $auditService
    ): RedirectResponse {
        try {
            $user = $authService->register($request->validated(), $request);
        } catch (Throwable) {
            return back()
                ->withInput($request->safe()->only(['username', 'email']))
                ->withErrors(['username' => config('security_errors.auth.register_failed.code').': '.config('security_errors.auth.register_failed.userInfo')]);
        }

        EmailOtpVerificationController::rememberPendingUser($request, $user);
        $emailOtp->send($user, $request, 'registration');
        $auditService->log($request, 'email_otp.sent', 'info', 202, $user->id, [
            'purpose' => 'registration',
        ]);

        return redirect()->route('register.email-otp.show')->with('status', 'Cuenta creada. Verifica tu correo con el OTP.');
    }
}
