<?php

namespace App\Http\Requests\Mfa;

use Illuminate\Foundation\Http\FormRequest;

class TotpCodeRequest extends FormRequest
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

        return [
            'otp' => ['required', 'string', 'regex:/^\d{'.$digits.'}$/'],
        ];
    }
}
