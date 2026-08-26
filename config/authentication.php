<?php

return [
    'token' => [
        'default_name' => env('AUTH_TOKEN_NAME', 'invoicehub-web'),
        'expires_in_minutes' => (int) env('AUTH_TOKEN_EXPIRES_IN', 1440),
        'remember_expires_in_minutes' => (int) env('AUTH_TOKEN_REMEMBER_EXPIRES_IN', 43200),
    ],

    /*
     | Masa berlaku tautan reset diambil dari broker bawaan Laravel
     | (config/auth.php -> passwords.users.expire) supaya hanya ada satu sumber angka.
     */
    'password_reset' => [
        'url' => env(
            'FRONTEND_PASSWORD_RESET_URL',
            rtrim((string) env('FRONTEND_URL', 'https://invoicehub.my.id'), '/').'/reset-password',
        ),
    ],

    'email_verification' => [
        'expires_in_minutes' => (int) env('EMAIL_VERIFICATION_EXPIRES_IN', 60),
    ],

    'frontend' => [
        'email_verified_url' => env(
            'FRONTEND_EMAIL_VERIFIED_URL',
            rtrim((string) env('FRONTEND_URL', 'https://invoicehub.my.id'), '/').'/login?email_verified=1',
        ),
    ],
];
