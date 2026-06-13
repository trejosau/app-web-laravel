<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminReauthRequest;
use App\Services\SecurityAuditService;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class ReauthController extends Controller
{
    public function show(): View
    {
        return view('admin.reauth');
    }

    public function store(
        AdminReauthRequest $request,
        TotpService $totpService,
        SecurityAuditService $auditService
    ): RedirectResponse {
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

            return back()->withErrors(['password' => config('security_errors.rbac.admin_reauth_failed.code').': '.config('security_errors.rbac.admin_reauth_failed.userInfo')]);
        }

        $user->forceFill([
            'totp_last_used_counter' => $usedCounter,
        ])->save();

        $request->session()->put('admin_reauthenticated_at', now()->timestamp);
        $auditService->log($request, 'admin.reauthenticated', 'info', 200, $user->id);

        return redirect()->intended(route('admin.users.index'))->with('status', 'Reautenticacion completada.');
    }
}
