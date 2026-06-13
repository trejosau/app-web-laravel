<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class AccountReauthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'password' => ['required', 'string'],
        ];

        if ((int) ($this->user()?->role?->required_mfa_level ?? 1) > 1) {
            $rules['otp'] = ['required', 'digits:'.config('totp.digits', 6)];
        }

        return $rules;
    }
}
