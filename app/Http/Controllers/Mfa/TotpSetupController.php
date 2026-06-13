<?php

namespace App\Http\Controllers\Mfa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mfa\UpdateTotpRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaPendingSessionService;
use App\Services\SecurityAuditService;
use App\Services\TotpService;
use App\Services\UserSessionService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class TotpSetupController extends Controller
{
    private const UPDATE_VERIFIED_AT = 'totp_update_verified_at';

    public function show(
        Request $request,
        TotpService $totpService,
        MfaPendingSessionService $mfaPendingSession
    ): View {
        $user = $this->resolveUser($request, $mfaPendingSession);
        abort_unless($user !== null && in_array($user->role?->name, [Role::USER, Role::ADMIN], true), 403);

        $isUpdate = $this->isUpdate($request);

        if ($isUpdate && ! $this->hasFreshUpdateVerification($request)) {
            $request->session()->forget(['totp_setup_secret', 'totp_setup_started_at']);

            return view('auth.totp-setup', [
                'secret' => null,
                'isUpdate' => true,
                'requiresUpdateVerification' => true,
            ]);
        }

        if (! $request->session()->has('totp_setup_secret')) {
            $request->session()->put([
                'totp_setup_secret' => $totpService->generateSecret(),
                'totp_setup_started_at' => now()->timestamp,
            ]);
        }

        $secret = (string) $request->session()->get('totp_setup_secret');

        return view('auth.totp-setup', [
            'secret' => $secret,
            'isUpdate' => $isUpdate,
            'requiresUpdateVerification' => false,
        ]);
    }

    public function qr(
        Request $request,
        TotpService $totpService,
        MfaPendingSessionService $mfaPendingSession
    ): Response {
        $user = $this->resolveUser($request, $mfaPendingSession);
        $secret = $request->session()->get('totp_setup_secret');
        $startedAt = (int) $request->session()->get('totp_setup_started_at', 0);
        $isUpdate = $this->isUpdate($request);

        abort_if($isUpdate && ! $this->hasFreshUpdateVerification($request), 404);
        abort_unless($user !== null && is_string($secret), 404);
        abort_if($startedAt < now()->subMinutes(10)->timestamp, 404);

        $writer = new Writer(new ImageRenderer(
            new RendererStyle(280, 4),
            new SvgImageBackEnd
        ));

        return response($writer->writeString($totpService->otpauthUri($user, $secret)), 200)
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function confirm(
        UpdateTotpRequest $request,
        TotpService $totpService,
        MfaPendingSessionService $mfaPendingSession,
        SecurityAuditService $auditService,
        UserSessionService $sessions
    ): RedirectResponse {
        $user = $this->resolveUser($request, $mfaPendingSession);
        abort_unless($user !== null && in_array($user->role?->name, [Role::USER, Role::ADMIN], true), 403);

        $secret = $request->session()->get('totp_setup_secret');
        $startedAt = (int) $request->session()->get('totp_setup_started_at', 0);
        $isUpdate = $this->isUpdate($request);

        if ($isUpdate && ! $this->hasFreshUpdateVerification($request)) {
            $validPassword = Hash::check($request->validated('current_password'), $user->password);
            $currentCounter = filled($user->totp_secret)
                ? $totpService->verify($user->totp_secret, $request->validated('current_otp'), $user->totp_last_used_counter)
                : null;

            if (! $validPassword || $currentCounter === null) {
                $auditService->log($request, 'totp.failed', 'warning', 422, $user->id, ['reason' => 'update_verification_failed']);

                if ($validPassword && $this->isUsedTotpCode($totpService, $user, $request->validated('current_otp'))) {
                    return back()
                        ->withErrors(['current_otp' => config('security_errors.mfa.totp_reused.code').': '.config('security_errors.mfa.totp_reused.userInfo')])
                        ->with('error', 'No se pudo actualizar el TOTP.');
                }

                return back()
                    ->withErrors(['current_otp' => config('security_errors.mfa.totp_update_required_verification.code').': '.config('security_errors.mfa.totp_update_required_verification.userInfo')])
                    ->with('error', 'No se pudo actualizar el TOTP.');
            }

            $user->forceFill([
                'totp_last_used_counter' => $currentCounter,
            ])->save();

            $request->session()->put(self::UPDATE_VERIFIED_AT, now()->timestamp);
            $request->session()->forget(['totp_setup_secret', 'totp_setup_started_at']);

            return redirect()
                ->route('totp.setup')
                ->with('status', 'Verificacion actual confirmada. Configura el nuevo TOTP.');
        }

        if (! is_string($secret) || $startedAt < now()->subMinutes(10)->timestamp) {
            $request->session()->forget(['totp_setup_secret', 'totp_setup_started_at']);
            $auditService->log($request, 'totp.failed', 'warning', 422, $user->id, ['reason' => 'setup_expired']);

            return back()
                ->withErrors(['otp' => config('security_errors.mfa.totp_expired.code').': '.config('security_errors.mfa.totp_expired.userInfo')])
                ->with('error', $isUpdate ? 'No se pudo actualizar el TOTP.' : 'No se pudo activar el TOTP.');
        }

        if ($totpService->verify($secret, $request->validated('otp')) === null) {
            $auditService->log($request, 'totp.failed', 'warning', 422, $user->id);

            return back()
                ->withErrors(['otp' => config('security_errors.mfa.invalid_totp.code').': '.config('security_errors.mfa.invalid_totp.userInfo')])
                ->with('error', $isUpdate ? 'No se pudo actualizar el TOTP.' : 'No se pudo activar el TOTP.');
        }

        $recoveryCodes = $totpService->enableForUser($user, $secret);
        $request->session()->forget(['totp_setup_secret', 'totp_setup_started_at', self::UPDATE_VERIFIED_AT]);
        $auditService->log($request, $isUpdate ? 'totp.updated' : 'totp.enabled', 'info', 200, $user->id);

        if ($isUpdate) {
            return redirect()
                ->route('profile.show')
                ->with('status', config('security_errors.mfa.totp_updated.userInfo'));
        }

        if ($mfaPendingSession->isPending($request)) {
            $level = $mfaPendingSession->pendingLevel($request);

            if ($level >= 3 || $user->role?->name === Role::ADMIN) {
                $mfaPendingSession->markTotpVerified($request);

                return redirect()
                    ->route('mfa.recovery-codes')
                    ->with('recovery_codes', $recoveryCodes)
                    ->with('status', 'TOTP activado. Guarda tus recovery codes.');
            }

            $mfaPendingSession->clear($request);
            $sessionTrace = $sessions->completeLogin($user, $request, 2);

            $auditService->log($request, 'login.success', 'info', 200, $user->id, $sessionTrace + [
                'mfa_level' => 2,
                'role' => $user->role?->name,
            ]);

            return redirect()->route('mfa.recovery-codes')->with('recovery_codes', $recoveryCodes);
        }

        return redirect()->route('mfa.recovery-codes')->with('recovery_codes', $recoveryCodes);
    }

    private function resolveUser(Request $request, MfaPendingSessionService $mfaPendingSession): ?User
    {
        return $request->user()
            ? $request->user()->loadMissing('role')
            : $mfaPendingSession->pendingUser($request);
    }

    private function isUpdate(Request $request): bool
    {
        return $request->user()?->totp_enabled_at !== null;
    }

    private function hasFreshUpdateVerification(Request $request): bool
    {
        $verifiedAt = (int) $request->session()->get(self::UPDATE_VERIFIED_AT, 0);

        return $verifiedAt >= now()->subMinutes(10)->timestamp;
    }

    private function isUsedTotpCode(TotpService $totpService, User $user, string $otp): bool
    {
        if (! filled($user->totp_secret) || $user->totp_last_used_counter === null) {
            return false;
        }

        $counter = $totpService->verify($user->totp_secret, $otp);

        return $counter !== null && $counter <= $user->totp_last_used_counter;
    }
}
