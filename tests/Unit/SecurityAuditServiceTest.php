<?php

namespace Tests\Unit;

use App\Services\SecurityAuditService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SecurityAuditServiceTest extends TestCase
{
    public function test_it_removes_sensitive_metadata(): void
    {
        $service = new SecurityAuditService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeMetadata');

        $metadata = $method->invoke($service, [
            'username' => 'safe_user',
            'password' => 'secret',
            'otp' => '123456',
            'auth_token' => 'token-value',
            'auth_token_fingerprint' => 'safe-fingerprint',
            'nested' => [
                'challenge' => 'challenge-value',
                'route_name' => 'login',
            ],
        ]);

        $this->assertSame('safe_user', $metadata['username']);
        $this->assertArrayNotHasKey('password', $metadata);
        $this->assertArrayNotHasKey('otp', $metadata);
        $this->assertArrayNotHasKey('auth_token', $metadata);
        $this->assertSame('safe-fingerprint', $metadata['auth_token_fingerprint']);
        $this->assertArrayNotHasKey('challenge', $metadata['nested']);
        $this->assertSame('login', $metadata['nested']['route_name']);
    }
}
