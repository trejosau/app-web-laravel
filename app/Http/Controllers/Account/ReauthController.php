<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAccountReauthenticated;
use App\Http\Requests\Account\AccountReauthRequest;
use App\Services\SecurityAuditService;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class ReauthController extends Controller
{
    public function show(): View
    {
        return view('account.reauth', [
            'requiresTotp' => (int) (request()->user()?->role?->required_mfa_level ?? 1) > 1,
        ]);
    }

    public function store(
        AccountReauthRequest $request,
        TotpService $totpService,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $request->user();
        $requiresTotp = (int) ($user->role?->required_mfa_level ?? 1) > 1;

        $validPassword = Hash::check($request->validated('password'), $user->password);
        $usedCounter = null;

        if ($requiresTotp && filled($user->totp_secret)) {
            $usedCounter = $totpService->verify($user->totp_secret, $request->validated('otp'), $user->totp_last_used_counter);
        }

        if (! $validPassword || ($requiresTotp && $usedCounter === null)) {
            $auditService->log($request, 'account.reauth_failed', 'warning', 422, $user->id);

            return back()->withErrors([
                'password' => config('security_errors.account.reauth_failed.code').': '.config('security_errors.account.reauth_failed.userInfo'),
            ]);
        }

        if ($usedCounter !== null) {
            $user->forceFill(['totp_last_used_counter' => $usedCounter])->save();
        }

        $request->session()->put(EnsureAccountReauthenticated::SESSION_KEY, now()->timestamp);
        $auditService->log($request, 'account.reauthenticated', 'info', 200, $user->id);

        return redirect()->intended(route('profile.show'))->with('status', 'Reautenticacion completada.');
    }
}
