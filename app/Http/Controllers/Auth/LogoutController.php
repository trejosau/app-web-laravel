<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        SecurityAuditService $auditService
    ): RedirectResponse {
        $userId = Auth::id() ?: $request->session()->get('auth_pending_user_id');

        Auth::logout();
        $mfaPendingSession->clear($request);
        $request->session()->forget(['auth_completed_mfa_level', 'admin_reauthenticated_at', 'admin_last_activity_at']);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $auditService->log($request, 'logout.success', 'info', 200, $userId ?: null);

        return redirect()->route('login');
    }
}
