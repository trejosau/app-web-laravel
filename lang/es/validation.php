<?php

return [
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'digits' => ':attribute debe tener :digits dígitos.',
    'email' => ':attribute no tiene un formato válido.',
    'max' => [
        'string' => ':attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'prohibited' => ':attribute no está permitido.',
    'password' => [
        'letters' => ':attribute debe incluir al menos una letra.',
        'mixed' => ':attribute debe incluir mayúsculas y minúsculas.',
        'numbers' => ':attribute debe incluir al menos un número.',
        'symbols' => ':attribute debe incluir al menos un símbolo.',
        'uncompromised' => ':attribute no es segura. Usa otra.',
    ],
    'regex' => ':attribute tiene un formato inválido.',
    'required' => ':attribute es obligatorio.',
    'string' => ':attribute debe ser texto.',
    'unique' => ':attribute ya está registrado.',

    'attributes' => [
        'code' => 'código',
        'current_otp' => 'código TOTP actual',
        'current_password' => 'contraseña actual',
        'email' => 'correo',
        'otp' => 'código TOTP',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'token' => 'PIN',
        'username' => 'usuario',
    ],
];
