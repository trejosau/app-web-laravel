<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class AuthService
{
    public function __construct(
        private readonly SecurityAuditService $auditService,
        private readonly MfaPendingSessionService $mfaPendingSession,
        private readonly UserSessionService $sessions
    ) {}

    /**
     * Validate credentials and complete login only when the required MFA level is done.
     *
     * @param  array{username: string, password: string}  $data
     * @return array{credentials_valid: bool, authenticated: bool, mfa_pending: bool}
     */
    public function login(array $data, Request $request): array
    {
        $username = $this->normalizeUsername($data['username']);
        $user = User::query()
            ->with('role')
            ->where('username', $username)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->auditService->log($request, 'login.failed', 'warning', 422, $user?->id, [
                'identifier_fingerprint' => $this->fingerprint($username),
                'known_user' => $user !== null,
                'stage' => 'credentials',
            ]);

            return ['credentials_valid' => false, 'authenticated' => false, 'mfa_pending' => false];
        }

        if ($user->status !== 'active' || ($user->locked_until && $user->locked_until->isFuture())) {
            $this->auditService->log($request, 'login.failed', 'warning', 403, $user->id, [
                'identifier_fingerprint' => $this->fingerprint($username),
                'stage' => 'account_state',
            ]);

            return ['credentials_valid' => false, 'authenticated' => false, 'mfa_pending' => false];
        }

        $mfaLevel = (int) ($user->role?->required_mfa_level ?? 1);

        if ($mfaLevel > 1) {
            $this->mfaPendingSession->start($user, $request, $mfaLevel);
            $this->auditService->log($request, 'login.mfa_required', 'info', 202, $user->id, [
                'mfa_level' => $mfaLevel,
                'role' => $user->role?->name,
                'identifier_fingerprint' => $this->fingerprint($username),
            ]);

            return ['credentials_valid' => true, 'authenticated' => false, 'mfa_pending' => true];
        }

        $sessionTrace = $this->sessions->completeLogin($user, $request, 1);

        $this->auditService->log($request, 'login.success', 'info', 200, $user->id, $sessionTrace + [
            'mfa_level' => $mfaLevel,
            'role' => $user->role?->name,
        ]);

        return ['credentials_valid' => true, 'authenticated' => true, 'mfa_pending' => false];
    }

    /**
     * Register a public user as guest with an Argon2id password hash.
     *
     * @param  array{username: string, email: string, password: string}  $data
     */
    public function register(array $data, Request $request): User
    {
        try {
            $user = DB::transaction(function () use ($data): User {
                $role = Role::default();

                if ($role === null) {
                    throw new RuntimeException('Default role is not configured.');
                }

                $user = new User([
                    'username' => $this->normalizeUsername($data['username']),
                    'email' => $this->normalizeEmail($data['email']),
                    'password' => Hash::make($data['password']),
                ]);

                $user->role_id = $role->id;
                $user->status = 'active';
                $user->password_changed_at = now();
                $user->save();

                return $user;
            });

            $user->loadMissing('role');

            $this->auditService->log($request, 'user.registered', 'info', 201, $user->id, [
                'actor' => 'guest',
                'target_role' => $user->role?->name ?? Role::GUEST,
                'registered_user_fingerprint' => $this->fingerprint($user->username),
            ]);

            return $user;
        } catch (Throwable $exception) {
            $this->auditService->log($request, 'register.failed', 'warning', 500, null, [
                'identifier_fingerprint' => $this->fingerprint($this->normalizeUsername((string) ($data['username'] ?? ''))),
                'stage' => 'persist',
            ]);

            throw $exception;
        }
    }

    /**
     * Normalize usernames before validation, lookup and audit fingerprinting.
     */
    private function normalizeUsername(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    /**
     * Normalize emails before persistence.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Create a non-reversible identifier for audit correlation.
     */
    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
