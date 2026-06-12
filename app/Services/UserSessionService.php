<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserSessionService
{
    /**
     * Return persisted sessions for the given user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user, Request $request): array
    {
        if (! $this->canUseDatabaseSessions()) {
            return [];
        }

        return DB::table($this->table())
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => Carbon::createFromTimestamp((int) $session->last_activity),
                'current' => hash_equals((string) $session->id, $request->session()->getId()),
            ])
            ->all();
    }

    /**
     * Complete the authenticated session only after the final factor succeeds.
     *
     * @return array{current_session_fingerprint: string, revoked_sessions: int, revoked_session_fingerprints: array<int, string>}
     */
    public function completeLogin(User $user, Request $request, int $mfaLevel): array
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_completed_mfa_level', $mfaLevel);

        if ($mfaLevel >= 3) {
            $request->session()->put('admin_last_activity_at', now()->timestamp);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $revoked = $this->revokeOtherSessions($user, $request);

        return [
            'current_session_fingerprint' => $this->fingerprint($request->session()->getId()),
            'revoked_sessions' => $revoked['count'],
            'revoked_session_fingerprints' => $revoked['fingerprints'],
        ];
    }

    /**
     * Delete every persisted session for the user except the current one.
     */
    public function deleteOtherSessions(User $user, Request $request): int
    {
        return $this->revokeOtherSessions($user, $request)['count'];
    }

    /**
     * Create a non-reversible HMAC fingerprint for session/token tracing.
     */
    public function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    /**
     * Revoke other sessions and return safe fingerprints for audit logs.
     *
     * @return array{count: int, fingerprints: array<int, string>}
     */
    private function revokeOtherSessions(User $user, Request $request): array
    {
        if (! $this->canUseDatabaseSessions()) {
            return ['count' => 0, 'fingerprints' => []];
        }

        $sessionIds = DB::table($this->table())
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        if ($sessionIds === []) {
            return ['count' => 0, 'fingerprints' => []];
        }

        $deleted = DB::table($this->table())
            ->whereIn('id', $sessionIds)
            ->delete();

        return [
            'count' => $deleted,
            'fingerprints' => array_map(fn (string $id): string => $this->fingerprint($id), $sessionIds),
        ];
    }

    private function canUseDatabaseSessions(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable($this->table());
    }

    private function table(): string
    {
        return (string) config('session.table', 'sessions');
    }
}
