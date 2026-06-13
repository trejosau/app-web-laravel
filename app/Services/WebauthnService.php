<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Throwable;

class WebauthnService
{
    private const REGISTER_CHALLENGE = 'webauthn_register_challenge';

    private const REGISTER_CHALLENGE_AT = 'webauthn_register_challenge_at';

    private const LOGIN_CHALLENGE = 'webauthn_login_challenge';

    private const LOGIN_CHALLENGE_AT = 'webauthn_login_challenge_at';

    public function __construct(
        private readonly MfaPendingSessionService $mfaPendingSession,
        private readonly SecurityAuditService $auditService,
        private readonly UserSessionService $sessions
    ) {}

    public function registrationOptions(User $user, Request $request): object
    {
        $server = $this->server();
        $excludeCredentialIds = $user->webauthnCredentials()
            ->get()
            ->map(fn (WebauthnCredential $credential): string => $credential->credential_id)
            ->all();

        $options = $server->getCreateArgs(
            $this->userHandle($user),
            $user->username,
            $user->username,
            (int) config('webauthn.timeout'),
            'preferred',
            (string) config('webauthn.user_verification'),
            null,
            $excludeCredentialIds
        );

        $request->session()->put([
            self::REGISTER_CHALLENGE => base64_encode($server->getChallenge()->getBinaryString()),
            self::REGISTER_CHALLENGE_AT => now()->timestamp,
        ]);

        return $options;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function register(User $user, Request $request, array $payload): void
    {
        $challenge = $request->session()->pull(self::REGISTER_CHALLENGE);
        $startedAt = (int) $request->session()->pull(self::REGISTER_CHALLENGE_AT, 0);

        if (! is_string($challenge) || $this->challengeExpired($startedAt)) {
            throw new WebAuthnException('missing registration challenge');
        }

        $clientDataJson = $this->base64UrlDecode(data_get($payload, 'response.clientDataJSON'));
        $attestationObject = $this->base64UrlDecode(data_get($payload, 'response.attestationObject'));

        $data = $this->server()->processCreate(
            $clientDataJson,
            $attestationObject,
            base64_decode($challenge, true) ?: '',
            $this->requiresUserVerification(),
            true,
            false
        );

        DB::transaction(function () use ($user, $data, $payload): void {
            $credentialId = $data->credentialId;
            $credentialIdHash = WebauthnCredential::makeCredentialIdHash($credentialId);

            if (WebauthnCredential::query()->where('credential_id_hash', $credentialIdHash)->exists()) {
                throw new WebAuthnException('credential already registered');
            }

            $user->webauthnCredentials()->create([
                'name' => 'Passkey',
                'credential_id_hash' => $credentialIdHash,
                'credential_id' => $credentialId,
                'public_key' => $data->credentialPublicKey,
                'sign_count' => (int) ($data->signatureCounter ?? 0),
                'transports' => $this->safeTransports((array) data_get($payload, 'response.transports', [])),
                'last_used_at' => now(),
            ]);

            $user->forceFill([
                'webauthn_enabled_at' => now(),
                'last_login_at' => now(),
            ])->save();
        });
    }

    public function authenticationOptions(User $user, Request $request): object
    {
        $credentialIds = $user->webauthnCredentials()
            ->get()
            ->map(fn (WebauthnCredential $credential): string => $credential->credential_id)
            ->all();

        if ($credentialIds === []) {
            throw new WebAuthnException('no passkey registered');
        }

        $server = $this->server();
        $options = $server->getGetArgs(
            $credentialIds,
            (int) config('webauthn.timeout'),
            true,
            true,
            true,
            true,
            true,
            (string) config('webauthn.user_verification')
        );

        $request->session()->put([
            self::LOGIN_CHALLENGE => base64_encode($server->getChallenge()->getBinaryString()),
            self::LOGIN_CHALLENGE_AT => now()->timestamp,
        ]);

        return $options;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function authenticate(User $user, Request $request, array $payload): void
    {
        $challenge = $request->session()->pull(self::LOGIN_CHALLENGE);
        $startedAt = (int) $request->session()->pull(self::LOGIN_CHALLENGE_AT, 0);

        if (! is_string($challenge) || $this->challengeExpired($startedAt)) {
            throw new WebAuthnException('missing authentication challenge');
        }

        $credentialId = $this->base64UrlDecode(data_get($payload, 'rawId') ?: data_get($payload, 'id'));
        $credential = WebauthnCredential::query()
            ->where('credential_id_hash', WebauthnCredential::makeCredentialIdHash($credentialId))
            ->first();

        if ($credential === null || $credential->user_id !== $user->id) {
            throw new WebAuthnException('passkey not found');
        }

        $server = $this->server();
        $server->processGet(
            $this->base64UrlDecode(data_get($payload, 'response.clientDataJSON')),
            $this->base64UrlDecode(data_get($payload, 'response.authenticatorData')),
            $this->base64UrlDecode(data_get($payload, 'response.signature')),
            $credential->public_key,
            base64_decode($challenge, true) ?: '',
            (int) $credential->sign_count,
            $this->requiresUserVerification(),
            true
        );

        $newCounter = $server->getSignatureCounter();

        $credential->forceFill([
            'sign_count' => $newCounter ?? $credential->sign_count,
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * Complete an admin session after TOTP and WebAuthn both succeed.
     *
     * @return array{current_session_fingerprint: string, revoked_sessions: int, revoked_session_fingerprints: array<int, string>}
     */
    public function completeAdminLogin(User $user, Request $request): array
    {
        $this->mfaPendingSession->clear($request);

        return $this->sessions->completeLogin($user, $request, 3);
    }

    public function fail(Request $request, User $user, Throwable $exception): void
    {
        $this->auditService->log($request, 'webauthn.failed', 'warning', 422, $user->id, [
            'reason' => class_basename($exception),
        ]);
    }

    private function server(): WebAuthn
    {
        return new WebAuthn(
            (string) config('webauthn.rp_name'),
            (string) config('webauthn.rp_id'),
            ['none'],
            true
        );
    }

    private function userHandle(User $user): string
    {
        return hash_hmac('sha256', 'user:'.$user->id, (string) config('app.key'), true);
    }

    private function requiresUserVerification(): bool
    {
        return config('webauthn.user_verification') === 'required';
    }

    private function challengeExpired(int $startedAt): bool
    {
        return $startedAt <= 0 || $startedAt < now()->subSeconds((int) config('webauthn.timeout'))->timestamp;
    }

    private function base64UrlDecode(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new WebAuthnException('invalid credential payload');
        }

        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        if ($decoded === false) {
            throw new WebAuthnException('invalid base64url payload');
        }

        return $decoded;
    }

    /**
     * @param  array<int, mixed>  $transports
     * @return array<int, string>
     */
    private function safeTransports(array $transports): array
    {
        $allowed = ['usb', 'nfc', 'ble', 'hybrid', 'internal'];

        return array_values(array_intersect($allowed, array_filter($transports, 'is_string')));
    }
}
