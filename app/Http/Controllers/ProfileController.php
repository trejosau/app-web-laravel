<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateRecoveryEmailRequest;
use App\Http\Middleware\EnsureAccountReauthenticated;
use App\Models\User;
use App\Services\SecurityAuditService;
use App\Services\UserSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request, UserSessionService $sessions): View
    {
        $user = $request->user()->load(['role', 'webauthnCredentials']);

        return view('profile.show', [
            'user' => $user,
            'sessions' => $sessions->forUser($user, $request),
        ]);
    }

    public function updatePassword(
        UpdatePasswordRequest $request,
        UserSessionService $sessions,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            $auditService->log($request, 'password.change_failed', 'warning', 422, $user->id);

            return back()->withErrors([
                'current_password' => config('security_errors.account.current_password_invalid.code').': '.config('security_errors.account.current_password_invalid.userInfo'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $sessions->deleteOtherSessions($user, $request);
        $request->session()->forget(EnsureAccountReauthenticated::SESSION_KEY);
        $request->session()->regenerate();

        $auditService->log($request, 'password.changed', 'info', 200, $user->id);

        return back()->with('status', 'Contrasena actualizada.');
    }

    public function updateEmail(
        UpdateRecoveryEmailRequest $request,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $request->user();

        $user->forceFill([
            'email' => $request->validated('email'),
            'email_verified_at' => null,
        ])->save();

        $auditService->log($request, 'recovery_email.updated', 'info', 200, $user->id);

        return $this->sendEmailVerification($request, $auditService);
    }

    public function sendEmailVerification(
        Request $request,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $request->user();

        if (! filled($user->email)) {
            return back()->withErrors(['email' => config('security_errors.account.recovery_email_missing.code').': '.config('security_errors.account.recovery_email_missing.userInfo')]);
        }

        if ($user->email_verified_at !== null) {
            return back()->with('status', 'El correo ya esta verificado.');
        }

        $url = URL::temporarySignedRoute(
            'profile.email.verify',
            now()->addMinutes(30),
            ['user' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::raw("Verifica tu correo de recuperacion:\n\n{$url}", function (Message $message) use ($user): void {
            $message->to($user->email)->subject('Verifica tu correo de recuperacion');
        });

        $auditService->log($request, 'recovery_email.verification_sent', 'info', 200, $user->id);

        return back()->with('status', 'Correo de verificacion enviado.');
    }

    public function verifyEmail(
        Request $request,
        User $user,
        string $hash,
        SecurityAuditService $auditService
    ): RedirectResponse {
        abort_unless($request->user()->is($user), 403);

        if (! filled($user->email) || ! hash_equals(sha1($user->email), $hash)) {
            return redirect()->route('profile.show')->withErrors([
                'email' => config('security_errors.account.email_verification_invalid.code').': '.config('security_errors.account.email_verification_invalid.userInfo'),
            ]);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        $auditService->log($request, 'recovery_email.verified', 'info', 200, $user->id);

        return redirect()->route('profile.show')->with('status', 'Correo verificado.');
    }

    public function destroyOtherSessions(
        Request $request,
        UserSessionService $sessions,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $sessions->deleteOtherSessions($request->user(), $request);

        $auditService->log($request, 'sessions.revoked', 'info', 200, $request->user()->id);

        return back()->with('status', 'Sesiones cerradas.');
    }
}
