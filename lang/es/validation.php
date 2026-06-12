<?php

return [
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'digits' => ':attribute debe tener :digits digitos.',
    'email' => ':attribute no tiene un formato valido.',
    'max' => [
        'string' => ':attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'prohibited' => ':attribute no esta permitido.',
    'password' => [
        'letters' => ':attribute debe incluir al menos una letra.',
        'mixed' => ':attribute debe incluir mayusculas y minusculas.',
        'numbers' => ':attribute debe incluir al menos un numero.',
        'symbols' => ':attribute debe incluir al menos un simbolo.',
        'uncompromised' => ':attribute no es segura. Usa otra.',
    ],
    'regex' => ':attribute tiene un formato invalido.',
    'required' => ':attribute es obligatorio.',
    'string' => ':attribute debe ser texto.',
    'unique' => ':attribute ya esta registrado.',

    'attributes' => [
        'code' => 'codigo',
        'current_otp' => 'codigo TOTP actual',
        'current_password' => 'contrasena actual',
        'email' => 'correo',
        'otp' => 'codigo TOTP',
        'password' => 'contrasena',
        'password_confirmation' => 'confirmacion de contrasena',
        'token' => 'PIN',
        'username' => 'usuario',
    ],
];
