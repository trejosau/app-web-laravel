<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminReauthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'otp' => ['required', 'digits:'.config('totp.digits', 6)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Ingresa tu contraseña actual.',
            'otp.required' => 'Ingresa tu codigo TOTP.',
            'otp.digits' => 'El codigo TOTP debe tener :digits digitos.',
        ];
    }
}
