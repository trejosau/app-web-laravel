<?php

return [
    'issuer' => env('TOTP_ISSUER', env('APP_NAME', 'Login Seguro')),
    'period' => (int) env('TOTP_PERIOD', 30),
    'digits' => (int) env('TOTP_DIGITS', 6),
    'window' => (int) env('TOTP_WINDOW', 1),
];
