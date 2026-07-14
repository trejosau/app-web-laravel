<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SecurityAuditService
{
    /**
     * Write a sanitized security audit event to the database and log files.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        Request $request,
        string $event,
        string $severity = 'info',
        ?int $status = null,
        int|string|null $userId = null,
        ?array $metadata = null
    ): void {
        $severity = $this->normalizeSeverity($severity);
        $metadata = $this->sanitizeMetadata($metadata);

        DB::transaction(function () use ($request, $event, $severity, $status, $userId, $metadata): void {
            $attributes = [
                'user_id' => $this->validUserId($userId),
                'event' => $event,
                'action' => $this->actionFor($event),
                'state' => $this->stateFor($event, $severity, $status),
                'severity' => $severity,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->path(),
                'method' => $request->method(),
                'status' => $status,
                'metadata' => $metadata,
            ];

            $persistedAttributes = $this->persistableAttributes($attributes);

            if (! $this->hasHashColumns()) {
                SecurityAuditLog::create($persistedAttributes);
                $this->writeSecurityLog($request, $attributes);

                return;
            }

            $previousHash = SecurityAuditLog::query()
                ->lockForUpdate()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('current_hash');

            $log = SecurityAuditLog::create($persistedAttributes + [
                'previous_hash' => $previousHash,
            ]);

            $log->forceFill([
                'current_hash' => $this->hashFor($log),
            ])->save();

            $this->writeSecurityLog($request, $attributes + [
                'id' => $log->id,
                'previous_hash' => $previousHash,
                'current_hash' => $log->current_hash,
            ]);
        });
    }

    /**
     * Write a normalized audit event when a named rate limiter blocks a request.
     */
    public function rateLimited(Request $request, string $limiter, int|string|null $userId = null): void
    {
        if ($limiter === 'login') {
            $this->log($request, 'login.rate_limited', 'warning', 429, $userId);
        }

        $this->log($request, 'rate_limit.blocked', 'warning', 429, $userId, [
            'limiter' => $limiter,
        ]);
    }

    private function validUserId(int|string|null $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return User::query()->whereKey($userId)->exists() ? (string) $userId : null;
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = Str::lower($severity);

        return in_array($severity, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], true)
            ? $severity
            : 'info';
    }

    private function actionFor(string $event): string
    {
        $event = Str::lower($event);

        return match (true) {
            Str::contains($event, ['created', 'registered', 'enabled', 'requested', 'request_', 'sent', 'generated']) => 'create',
            Str::contains($event, ['updated', 'changed', 'verified', 'reauthenticated', 'regenerated', 'activated', 'blocked']) => 'update',
            Str::contains($event, ['deleted', 'removed', 'revoked', 'expired']) => 'delete',
            Str::contains($event, ['restored']) => 'restore',
            Str::contains($event, ['viewed']) => 'view',
            Str::contains($event, ['logout']) => 'logout',
            Str::contains($event, ['login']) => 'login',
            Str::contains($event, ['approved']) => 'approve',
            Str::contains($event, ['cancelled', 'canceled']) => 'cancel',
            default => 'view',
        };
    }

    private function stateFor(string $event, string $severity, ?int $httpStatus): string
    {
        $event = Str::lower($event);

        if (Str::contains($event, ['cancelled', 'canceled'])) {
            return 'CANCELLED';
        }

        if ($httpStatus === 202 || Str::contains($event, ['pending', 'requested'])) {
            return 'PENDING';
        }

        if ($httpStatus !== null && $httpStatus >= 400 || Str::contains($event, ['failed', 'invalid', 'denied', 'blocked'])) {
            return 'FAILED';
        }

        if (in_array($severity, ['warning', 'critical', 'alert', 'emergency'], true)) {
            return 'WARNING';
        }

        return 'SUCCESS';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function writeSecurityLog(Request $request, array $attributes): void
    {
        $context = [
            'format' => 'security.audit.v1',
            'level' => $attributes['severity'],
            'action' => $attributes['action'],
            'state' => $attributes['state'],
            'event' => $attributes['event'],
            'user_id' => $attributes['user_id'],
            'http_status' => $attributes['status'],
            'ip' => $attributes['ip_address'],
            'method' => $attributes['method'],
            'route' => $attributes['route'],
            'request_id' => $request->headers->get('X-Request-Id'),
            'who' => [
                'user_id' => $attributes['user_id'],
                'actor' => $attributes['user_id'] === null ? 'guest' : 'user',
                'ip' => $attributes['ip_address'],
            ],
            'what' => [
                'event' => $attributes['event'],
                'action' => $attributes['action'],
                'state' => $attributes['state'],
            ],
            'when' => now()->toISOString(),
            'where' => [
                'route' => $attributes['route'],
                'method' => $attributes['method'],
                'ip' => $attributes['ip_address'],
                'user_agent' => $attributes['user_agent'],
            ],
            'metadata' => $attributes['metadata'],
        ];

        Log::channel('audit')->log((string) $attributes['severity'], 'security.audit', $context);

        if ($this->isAuthenticationEvent((string) $attributes['event'])) {
            Log::channel('auth_attempts')->log((string) $attributes['severity'], 'security.authentication', $context);
        }

        if ($this->shouldWriteToLaravelLog((string) $attributes['severity'], (string) $attributes['state'])) {
            Log::log((string) $attributes['severity'], 'security.audit', $context);
        }

        if ((bool) config('app.debug')) {
            Log::channel('development')->debug('security.audit.dev', $context);
        }
    }

    private function shouldWriteToLaravelLog(string $severity, string $state): bool
    {
        return in_array($severity, ['emergency', 'alert', 'critical', 'error', 'warning', 'debug'], true)
            || in_array($state, ['FAILED', 'WARNING', 'CANCELLED'], true);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $safe = [];

        foreach ($metadata as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            $safe[$key] = is_array($value)
                ? $this->sanitizeMetadata($value)
                : $this->safeValue($value);
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($this->isSafeTraceKey($key)) {
            return false;
        }

        return Str::contains(Str::lower($key), [
            'password',
            'otp',
            'totp',
            'secret',
            'token',
            'challenge',
            'recovery_code',
            'public_key',
            'assertion',
            'cookie',
            'session',
        ]);
    }

    private function isSafeTraceKey(string $key): bool
    {
        $key = Str::lower($key);

        return $key === 'revoked_sessions'
            || Str::contains($key, ['fingerprint'])
            || Str::endsWith($key, ['_count']);
    }

    private function isAuthenticationEvent(string $event): bool
    {
        return Str::contains(Str::lower($event), [
            'csrf',
            'login',
            'logout',
            'mfa',
            'password',
            'rate_limit',
            'recovery_code',
            'register',
            'session',
            'token',
            'totp',
        ]);
    }

    private function safeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return Str::limit($value, 255, '');
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function hasHashColumns(): bool
    {
        return Schema::hasColumn('security_audit_logs', 'previous_hash')
            && Schema::hasColumn('security_audit_logs', 'current_hash');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function persistableAttributes(array $attributes): array
    {
        if (! Schema::hasTable('security_audit_logs')) {
            return $attributes;
        }

        return array_intersect_key($attributes, array_flip(Schema::getColumnListing('security_audit_logs')));
    }

    private function hashFor(SecurityAuditLog $log): string
    {
        return hash('sha512', json_encode([
            'id' => $log->id,
            'user_id' => $log->user_id,
            'event' => $log->event,
            'action' => $log->action,
            'state' => $log->state,
            'severity' => $log->severity,
            'ip_address' => $log->ip_address,
            'route' => $log->route,
            'method' => $log->method,
            'status' => $log->status,
            'metadata' => $log->metadata,
            'previous_hash' => $log->previous_hash,
            'created_at' => optional($log->created_at)->toISOString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
