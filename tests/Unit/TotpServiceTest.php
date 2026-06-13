<?php

namespace Tests\Unit;

use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_it_generates_rfc6238_totp_codes(): void
    {
        config([
            'totp.period' => 30,
            'totp.digits' => 8,
            'totp.window' => 0,
        ]);

        $service = new TotpService;

        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $vectors = [
            59 => '94287082',
            1111111109 => '07081804',
            1111111111 => '14050471',
            1234567890 => '89005924',
            2000000000 => '69279037',
            20000000000 => '65353130',
        ];

        foreach ($vectors as $timestamp => $expected) {
            $this->assertSame($expected, $service->codeAt($secret, $timestamp));
        }
    }

    public function test_it_verifies_totp_codes(): void
    {
        config([
            'totp.period' => 30,
            'totp.digits' => 6,
            'totp.window' => 1,
        ]);

        $service = new TotpService;
        $secret = $service->generateSecret();
        $code = $service->codeAt($secret, time());

        $this->assertIsInt($service->verify($secret, $code));
        $this->assertNull($service->verify($secret, '000000'));
    }

    public function test_otpauth_uri_keeps_label_separator_compatible_with_authenticator_apps(): void
    {
        config(['totp.issuer' => 'Login Seguro']);

        $service = new TotpService;
        $user = new User(['username' => 'admin_user']);

        $uri = $service->otpauthUri($user, 'BASE32SECRET');

        $this->assertStringStartsWith('otpauth://totp/Login%20Seguro:admin_user?', $uri);
        $this->assertStringContainsString('secret=BASE32SECRET', $uri);
        $this->assertStringContainsString('issuer=Login%20Seguro', $uri);
    }

    public function test_it_rejects_replayed_totp_counter(): void
    {
        config([
            'totp.period' => 30,
            'totp.digits' => 6,
            'totp.window' => 1,
        ]);

        $service = new TotpService;
        $secret = $service->generateSecret();
        $timestamp = time();
        $counter = intdiv($timestamp, 30);
        $code = $service->codeAt($secret, $timestamp);

        $this->assertSame($counter, $service->verify($secret, $code));
        $this->assertNull($service->verify($secret, $code, $counter));
    }

    public function test_totp_secret_is_encrypted_on_user_model(): void
    {
        $user = new User;
        $user->forceFill([
            'totp_secret' => 'TOTPSECRET',
        ]);

        $this->assertNotSame('TOTPSECRET', $user->getAttributes()['totp_secret']);
        $this->assertSame('TOTPSECRET', $user->totp_secret);
    }

    public function test_recovery_code_hash_uses_laravel_hashing(): void
    {
        $hash = RecoveryCode::makeCodeHash('ABCDE-12345');
        $recoveryCode = new RecoveryCode([
            'code_hash' => $hash,
        ]);

        $this->assertNotSame('ABCDE-12345', $hash);
        $this->assertTrue($recoveryCode->matches('ABCDE-12345'));
        $this->assertFalse($recoveryCode->matches('WRONG-12345'));
    }
}
