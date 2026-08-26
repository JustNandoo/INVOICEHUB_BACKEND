<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     | Midtrans Snap. Sandbox dan produksi memakai kunci yang berbeda dan tidak
     | saling kompatibel, jadi cukup tukar nilai di .env saat naik ke produksi.
     |
     | Urutan 'enabled_payments' menentukan urutan tampil di popup Snap. QRIS
     | sengaja didahulukan karena biayanya paling murah bagi penerima (0,7%),
     | sedangkan kartu kredit paling mahal (2,9% + Rp2.000).
     */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'expiry_minutes' => (int) env('MIDTRANS_EXPIRY_MINUTES', 60),
        'finish_url' => env(
            'MIDTRANS_FINISH_URL',
            rtrim((string) env('FRONTEND_URL', 'https://invoicehub.my.id'), '/').'/settings/subscription',
        ),
        'enabled_payments' => [
            'other_qris', 'gopay', 'shopeepay',
            'bca_va', 'bni_va', 'bri_va', 'permata_va', 'echannel', 'other_va',
            'indomaret', 'alfamart',
            'credit_card',
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
