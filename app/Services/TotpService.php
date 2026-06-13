<?php

namespace App\Services;

use App\Models\RecoveryCode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = (string) config('totp.issuer');
        $label = rawurlencode($issuer).':'.rawurlencode($user->username);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'period' => config('totp.period'),
            'digits' => config('totp.digits'),
            'algorithm' => 'SHA1',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verify(string $secret, string $otp, ?int $lastUsedCounter = null): ?int
    {
        if (! preg_match('/^\d{'.config('totp.digits').'}$/', $otp)) {
            return null;
        }

        $time = time();
        $period = (int) config('totp.period');
        $window = (int) config('totp.window');

        for ($offset = -$window; $offset <= $window; $offset++) {
            $counterTime = $time + ($offset * $period);
            $counter = intdiv($counterTime, $period);

            if ($lastUsedCounter !== null && $counter <= $lastUsedCounter) {
                continue;
            }

            if (hash_equals($this->codeAt($secret, $counterTime), $otp)) {
                return $counter;
            }
        }

        return null;
    }

    public function codeAt(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, (int) config('totp.period'));
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad(
            (string) ($value % (10 ** (int) config('totp.digits'))),
            (int) config('totp.digits'),
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * @return array<int, string>
     */
    public function enableForUser(User $user, string $secret): array
    {
        return DB::transaction(function () use ($user, $secret): array {
            $user->forceFill([
                'totp_secret' => $secret,
                'totp_enabled_at' => now(),
                'totp_last_used_counter' => null,
            ])->save();

            return $this->replaceRecoveryCodes($user);
        });
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(fn (): array => $this->replaceRecoveryCodes($user));
    }

    /**
     * @return array<int, string>
     */
    private function replaceRecoveryCodes(User $user): array
    {
        $user->recoveryCodes()->delete();

        $codes = $this->generateRecoveryCodes();

        foreach ($codes as $code) {
            $user->recoveryCodes()->create([
                'code_hash' => RecoveryCode::makeCodeHash($code),
            ]);
        }

        return $codes;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        return Collection::times($count, function (): string {
            return Str::upper(Str::random(5).'-'.Str::random(5));
        })->all();
    }

    private function base32Encode(string $binary): string
    {
        $bits = '';
        $encoded = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';
        $binary = '';

        foreach (str_split($secret) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);

            if ($position === false) {
                throw new InvalidArgumentException('Invalid TOTP secret.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
