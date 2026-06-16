<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailOtpService
{
    private const CODE_KEY = 'email_otp_code_hash';

    private const EXPIRES_KEY = 'email_otp_expires_at';

    private const PURPOSE_KEY = 'email_otp_purpose';

    public function send(User $user, Request $request, string $purpose): void
    {
        if (! is_string($user->email) || trim($user->email) === '') {
            throw new RuntimeException('User email is required to send OTP.');
        }

        $code = (string) random_int(100000, 999999);

        $request->session()->put([
            self::CODE_KEY => $this->hash($code),
            self::EXPIRES_KEY => now()->addMinutes(10)->timestamp,
            self::PURPOSE_KEY => $purpose,
        ]);

        Mail::raw("Tu código OTP es: {$code}\n\nVence en 10 minutos.", function (Message $message) use ($user, $purpose): void {
            $subject = $purpose === 'registration'
                ? 'Verifica tu correo'
                : 'Código OTP de acceso';

            $message->to($user->email)->subject($subject);
        });
    }

    public function verify(Request $request, string $code, string $purpose): bool
    {
        $hash = $request->session()->get(self::CODE_KEY);
        $expiresAt = (int) $request->session()->get(self::EXPIRES_KEY, 0);
        $storedPurpose = $request->session()->get(self::PURPOSE_KEY);

        if (! is_string($hash) || $storedPurpose !== $purpose || $expiresAt < now()->timestamp) {
            return false;
        }

        return hash_equals($hash, $this->hash($code));
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::CODE_KEY,
            self::EXPIRES_KEY,
            self::PURPOSE_KEY,
        ]);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
