<?php

return [
    'rp_name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'Login Seguro')),
    'rp_id' => env('WEBAUTHN_RP_ID', 'localhost'),
    'origin' => env('WEBAUTHN_ORIGIN', env('APP_URL', 'http://localhost:8000')),
    'timeout' => (int) env('WEBAUTHN_TIMEOUT', 60),
    'user_verification' => env('WEBAUTHN_USER_VERIFICATION', 'required'),
];
