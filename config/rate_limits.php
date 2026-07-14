<?php

return [
    'api' => [
        'attempts' => (int) env('RATE_LIMIT_API_ATTEMPTS', 60),
        'decay_minutes' => (int) env('RATE_LIMIT_API_DECAY_MINUTES', 1),
        'key' => 'user_or_client',
    ],
    'register' => [
        'attempts' => (int) env('RATE_LIMIT_REGISTER_ATTEMPTS', 3),
        'decay_minutes' => (int) env('RATE_LIMIT_REGISTER_DECAY_MINUTES', 1),
        'key' => 'registration_identity_ip',
    ],
    'login' => [
        'attempts' => (int) env('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
        'decay_minutes' => (int) env('RATE_LIMIT_LOGIN_DECAY_MINUTES', 1),
        'key' => 'username_ip',
    ],
    'totp' => [
        'attempts' => (int) env('RATE_LIMIT_TOTP_ATTEMPTS', 5),
        'decay_minutes' => (int) env('RATE_LIMIT_TOTP_DECAY_MINUTES', 1),
        'key' => 'pending_user_ip',
    ],
    'webauthn' => [
        'attempts' => (int) env('RATE_LIMIT_WEBAUTHN_ATTEMPTS', 5),
        'decay_minutes' => (int) env('RATE_LIMIT_WEBAUTHN_DECAY_MINUTES', 1),
        'key' => 'pending_user_ip',
    ],
    'recovery-codes' => [
        'attempts' => (int) env('RATE_LIMIT_RECOVERY_CODES_ATTEMPTS', 3),
        'decay_minutes' => (int) env('RATE_LIMIT_RECOVERY_CODES_DECAY_MINUTES', 10),
        'key' => 'pending_user_ip',
    ],
    'admin' => [
        'attempts' => (int) env('RATE_LIMIT_ADMIN_ATTEMPTS', 25),
        'decay_minutes' => (int) env('RATE_LIMIT_ADMIN_DECAY_MINUTES', 1),
        'key' => 'pending_user_ip',
    ],
    'password-reset' => [
        'attempts' => (int) env('RATE_LIMIT_PASSWORD_RESET_ATTEMPTS', 3),
        'decay_minutes' => (int) env('RATE_LIMIT_PASSWORD_RESET_DECAY_MINUTES', 10),
        'key' => 'email_ip',
    ],
    'email-resend' => [
        'attempts' => (int) env('RATE_LIMIT_EMAIL_RESEND_ATTEMPTS', 3),
        'decay_minutes' => (int) env('RATE_LIMIT_EMAIL_RESEND_DECAY_MINUTES', 10),
        'key' => 'pending_user_ip',
    ],
    'admin-reauth' => [
        'attempts' => (int) env('RATE_LIMIT_ADMIN_REAUTH_ATTEMPTS', 5),
        'decay_minutes' => (int) env('RATE_LIMIT_ADMIN_REAUTH_DECAY_MINUTES', 10),
        'key' => 'pending_user_ip',
    ],
    'admin-critical' => [
        'attempts' => (int) env('RATE_LIMIT_ADMIN_CRITICAL_ATTEMPTS', 25),
        'decay_minutes' => (int) env('RATE_LIMIT_ADMIN_CRITICAL_DECAY_MINUTES', 1),
        'key' => 'pending_user_ip',
    ],
];
