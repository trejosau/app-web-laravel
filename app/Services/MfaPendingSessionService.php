<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class MfaPendingSessionService
{
    private const SESSION_KEYS = [
        'auth_pending_user_id',
        'auth_pending_level',
        'auth_pending_started_at',
        'auth_pending_totp_verified_at',
    ];

    /**
     * Store encrypted pending-MFA state without authenticating the user.
     */
    public function start(User $user, Request $request, int $mfaLevel): void
    {
        $request->session()->regenerate();

        $request->session()->put([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => $mfaLevel,
            'auth_pending_started_at' => now()->timestamp,
        ]);
    }

    /**
     * Remove all pending-MFA keys from the current encrypted session.
     */
    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEYS);
    }

    /**
     * Mark TOTP as completed while waiting for the final WebAuthn factor.
     */
    public function markTotpVerified(Request $request): void
    {
        $request->session()->put('auth_pending_totp_verified_at', now()->timestamp);
    }

    /**
     * Return the MFA level required by the pending login flow.
     */
    public function pendingLevel(Request $request): int
    {
        return (int) $request->session()->get('auth_pending_level', 0);
    }

    /**
     * Resolve the user attached to the pending encrypted MFA state.
     */
    public function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('auth_pending_user_id');

        return $userId ? User::query()->with('role')->find($userId) : null;
    }

    /**
     * Determine whether the current session is waiting for MFA completion.
     */
    public function isPending(Request $request): bool
    {
        return $request->session()->has('auth_pending_user_id')
            && $request->session()->has('auth_pending_level')
            && $request->session()->has('auth_pending_started_at');
    }

    /**
     * Determine whether the pending MFA state exceeded its allowed lifetime.
     */
    public function isExpired(Request $request): bool
    {
        $startedAt = (int) $request->session()->get('auth_pending_started_at', 0);

        return $startedAt < now()->subMinutes(5)->timestamp;
    }
}
