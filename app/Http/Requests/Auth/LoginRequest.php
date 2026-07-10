<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ValidatesRecaptcha;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $identifier = (string) $value;

                    if (str_contains($identifier, '@')) {
                        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                            $fail('Ingresa un correo electrónico válido.');
                        }

                        return;
                    }

                    if (mb_strlen($identifier) < 3 || ! preg_match('/^[a-z0-9_]+$/', $identifier)) {
                        $fail('El usuario debe tener al menos 3 caracteres y usar solo letras, números o guion bajo.');
                    }
                },
            ],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ];
    }
}
