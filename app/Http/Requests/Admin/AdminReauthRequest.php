<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminReauthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $digits = (int) config('totp.digits', 6);

        return [
            'password' => ['required', 'string'],
            'otp' => ['required', 'string', 'regex:/^\d{'.$digits.'}$/'],
        ];
    }
}
