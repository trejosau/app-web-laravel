<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ValidatesRecaptcha;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use ValidatesRecaptcha;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => mb_strtolower(trim((string) $this->input('username'))),
            ]);
        }

        if ($this->has('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'role_id' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'status' => ['prohibited'],
            'name' => ['prohibited'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        app(SecurityAuditService::class)->log($this, 'register.failed', 'warning', 422, null, [
            'fields' => array_keys($validator->failed()),
        ]);

        parent::failedValidation($validator);
    }
}
