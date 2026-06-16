<?php

namespace App\Http\Requests\Mfa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTotpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $digits = (int) config('totp.digits');

        if ($this->user()?->totp_enabled_at !== null && ! $this->hasFreshUpdateVerification()) {
            return [
                'current_password' => ['required', 'string'],
                'current_otp' => ['required', 'string', 'regex:/^\d{'.$digits.'}$/'],
            ];
        }

        return [
            'otp' => ['required', 'string', 'regex:/^\d{'.$digits.'}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $digits = (int) config('totp.digits');

        return [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_otp.required' => 'Ingresa tu codigo TOTP actual.',
            'current_otp.regex' => 'El codigo TOTP actual debe tener '.$digits.' digitos.',
            'otp.required' => 'Ingresa el codigo del nuevo TOTP.',
            'otp.regex' => 'El codigo del nuevo TOTP debe tener '.$digits.' digitos.',
        ];
    }

    private function hasFreshUpdateVerification(): bool
    {
        $verifiedAt = (int) $this->session()->get('totp_update_verified_at', 0);

        return $verifiedAt >= now()->subMinutes(10)->timestamp;
    }
}
