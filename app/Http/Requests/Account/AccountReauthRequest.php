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
        $digits = (int) config('totp.digits', 6);

        return [
            'password' => ['required', 'string'],
            'otp' => ['nullable', 'string', 'regex:/^\d{'.$digits.'}$/', 'required_if:requires_totp,true'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_totp' => (int) ($this->user()?->role?->required_mfa_level ?? 1) > 1,
        ]);
    }
}
