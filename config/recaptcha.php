<?php

/*
|--------------------------------------------------------------------------
| Google reCAPTCHA v2 ("No soy un robot")
|--------------------------------------------------------------------------
|
| 1. https://www.google.com/recaptcha/admin/create
| 2. Tipo: reCAPTCHA v2 -> "Casilla de verificación"
| 3. Dominios: localhost, 127.0.0.1 (y tu dominio en producción)
| 4. Copia Site key y Secret key aquí y RECAPTCHA_ENABLED=true
|
*/

return [
    'enabled' => (bool) env('RECAPTCHA_ENABLED', false),
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    'verify_url' => env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
];
