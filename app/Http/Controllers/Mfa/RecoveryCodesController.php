<?php

namespace App\Http\Controllers\Mfa;

use App\Http\Controllers\Controller;
use App\Services\SecurityAuditService;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecoveryCodesController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.recovery-codes', [
            'recoveryCodes' => session('recovery_codes', []),
        ]);
    }

    public function regenerate(
        Request $request,
        TotpService $totpService,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user !== null && $user->totp_enabled_at !== null, 403);

        $recoveryCodes = $totpService->regenerateRecoveryCodes($user);

        $auditService->log($request, 'recovery_codes.regenerated', 'info', 200, $user->id);

        return redirect()->route('mfa.recovery-codes')->with('recovery_codes', $recoveryCodes);
    }
}
