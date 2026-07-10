<?php

namespace App\Http\Controllers\Mfa;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAccountReauthenticated;
use App\Models\Role;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use App\Services\WebauthnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class WebauthnController extends Controller
{
    public function setup(Request $request, MfaPendingSessionService $mfaPendingSession): View
    {
        $this->registrationUser($request, $mfaPendingSession);

        return view('auth.webauthn-setup', [
            'title' => 'Agregar Passkey',
        ]);
    }

    public function login(Request $request, MfaPendingSessionService $mfaPendingSession): View
    {
        $user = $mfaPendingSession->pendingUser($request);

        abort_unless($user !== null && $request->session()->has('auth_pending_totp_verified_at'), 404);

        abort_unless($user->webauthn_enabled_at !== null && $user->webauthnCredentials()->exists(), 404);

        return view('auth.webauthn-login', [
            'title' => 'Verificar Passkey',
        ]);
    }

    public function registerOptions(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        WebauthnService $webauthnService
    ): JsonResponse {
        $user = $this->registrationUser($request, $mfaPendingSession);

        return response()->json($webauthnService->registrationOptions($user, $request));
    }

    public function register(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        WebauthnService $webauthnService,
        SecurityAuditService $auditService
    ): JsonResponse {
        $user = $this->registrationUser($request, $mfaPendingSession);
        $isPending = $mfaPendingSession->isPending($request);

        try {
            $webauthnService->register($user, $request, $request->all());
            $auditService->log($request, 'webauthn.registered', 'info', 201, $user->id);

            if ($isPending) {
                $sessionTrace = $webauthnService->completeAdminLogin($user, $request);
                $auditService->log($request, 'login.success', 'info', 200, $user->id, $sessionTrace + [
                    'mfa_level' => 3,
                    'role' => $user->role?->name,
                ]);
            }

            return response()->json([
                'redirect' => $isPending ? route('dashboard.admin') : route('profile.show'),
            ]);
        } catch (Throwable $exception) {
            $webauthnService->fail($request, $user, $exception);

            return response()->json([
                'code' => config('security_errors.passkey.validation_failed.code'),
                'message' => config('security_errors.passkey.validation_failed.userInfo'),
            ], 422);
        }
    }

    public function loginOptions(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        WebauthnService $webauthnService
    ): JsonResponse {
        $user = $this->pendingAdmin($request, $mfaPendingSession);

        return response()->json($webauthnService->authenticationOptions($user, $request));
    }

    public function authenticate(
        Request $request,
        MfaPendingSessionService $mfaPendingSession,
        WebauthnService $webauthnService,
        SecurityAuditService $auditService
    ): JsonResponse {
        $user = $this->pendingAdmin($request, $mfaPendingSession);

        try {
            $webauthnService->authenticate($user, $request, $request->all());
            $sessionTrace = $webauthnService->completeAdminLogin($user, $request);

            $auditService->log($request, 'webauthn.verified', 'info', 200, $user->id);
            $auditService->log($request, 'login.success', 'info', 200, $user->id, $sessionTrace + [
                'mfa_level' => 3,
                'role' => $user->role?->name,
            ]);

            return response()->json([
                'redirect' => route('dashboard.admin'),
            ]);
        } catch (Throwable $exception) {
            $webauthnService->fail($request, $user, $exception);

            return response()->json([
                'code' => config('security_errors.passkey.validation_failed.code'),
                'message' => config('security_errors.passkey.validation_failed.userInfo'),
            ], 422);
        }
    }

    public function destroy(Request $request, WebauthnCredential $credential, SecurityAuditService $auditService): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->hasRole(Role::ADMIN)
            && (int) $request->session()->get('auth_completed_mfa_level', 0) >= 3
            && $credential->user_id === $user->id,
            403
        );

        if ($user->webauthnCredentials()->count() <= 1) {
            return back()->withErrors(['passkey' => config('security_errors.passkey.required.code').': '.config('security_errors.passkey.required.userInfo')]);
        }

        $credential->delete();
        $auditService->log($request, 'webauthn.deleted', 'warning', 200, $user->id);

        return back()->with('status', config('security_errors.passkey.removed.userInfo'));
    }

    private function pendingAdmin(Request $request, MfaPendingSessionService $mfaPendingSession): User
    {
        $user = $mfaPendingSession->pendingUser($request);

        abort_unless($user !== null && $request->session()->has('auth_pending_totp_verified_at'), 404);
        abort_unless($mfaPendingSession->pendingLevel($request) >= 3, 403);

        return $user;
    }

    private function registrationUser(Request $request, MfaPendingSessionService $mfaPendingSession): User
    {
        if ($request->user() instanceof User) {
            $user = $request->user()->loadMissing('role');

            abort_unless($user->hasRole(Role::ADMIN), 403);
            abort_unless((int) $request->session()->get('auth_completed_mfa_level', 0) >= 3, 403);
            $this->ensureFreshAccountReauth($request);

            return $user;
        }

        return $this->pendingAdmin($request, $mfaPendingSession);
    }

    private function ensureFreshAccountReauth(Request $request): void
    {
        $verifiedAt = (int) $request->session()->get(EnsureAccountReauthenticated::SESSION_KEY, 0);

        if ($verifiedAt >= now()->subMinutes(10)->timestamp) {
            return;
        }

        if ($request->expectsJson()) {
            abort(403);
        }

        throw new HttpResponseException(redirect()->guest(route('account.reauth')));
    }
}
