<?php

return [
    'enabled' => (bool) env('MARKETPLACE_ENABLED', true),

    /*
     | Berapa hari ke belakang yang diambil saat sinkronisasi pertama.
     */
    'initial_lookback_days' => (int) env('MARKETPLACE_LOOKBACK_DAYS', 30),

    /*
     | Nilai default invoice yang dibuat dari pesanan marketplace.
     */
    'invoice' => [
        'due_days' => (int) env('MARKETPLACE_INVOICE_DUE_DAYS', 7),
        'status' => env('MARKETPLACE_INVOICE_STATUS', 'unpaid'),
    ],

    /*
     | Katalog platform.
     |
     | `manual` selalu tersedia dan tidak memerlukan persetujuan siapa pun: pengguna
     | mengunggah ekspor pesanan dari Seller Center. Platform lain memakai OAuth dan
     | baru aktif setelah kredensial partner diisi di .env.
     */
    'platforms' => [
        'manual' => [
            'label' => 'Impor Manual (CSV)',
            'kind' => 'import',
            'color' => 'gray',
            'description' => 'Unggah ekspor pesanan dari Seller Center mana pun.',
        ],
        'shopee' => [
            'label' => 'Shopee',
            'kind' => 'oauth',
            'color' => 'coral',
            'description' => 'Tarik pesanan langsung dari toko Shopee Anda.',
            'partner_id' => env('SHOPEE_PARTNER_ID'),
            'partner_key' => env('SHOPEE_PARTNER_KEY'),
            'base_url' => env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com'),
            'auth_path' => '/api/v2/shop/auth_partner',
        ],
        'tokopedia' => [
            'label' => 'Tokopedia',
            'kind' => 'oauth',
            'color' => 'green',
            'description' => 'Tarik pesanan dari toko Tokopedia Anda.',
            'client_id' => env('TOKOPEDIA_CLIENT_ID'),
            'client_secret' => env('TOKOPEDIA_CLIENT_SECRET'),
            'fs_id' => env('TOKOPEDIA_FS_ID'),
            'base_url' => env('TOKOPEDIA_BASE_URL', 'https://fs.tokopedia.net'),
            'auth_url' => env('TOKOPEDIA_AUTH_URL', 'https://accounts.tokopedia.com/token'),
        ],
        'lazada' => [
            'label' => 'Lazada',
            'kind' => 'oauth',
            'color' => 'blue',
            'description' => 'Tarik pesanan dari toko Lazada Anda.',
            'app_key' => env('LAZADA_APP_KEY'),
            'app_secret' => env('LAZADA_APP_SECRET'),
            'base_url' => env('LAZADA_BASE_URL', 'https://api.lazada.co.id/rest'),
            'auth_url' => env('LAZADA_AUTH_URL', 'https://auth.lazada.com/oauth/authorize'),
            'token_url' => env('LAZADA_TOKEN_URL', 'https://auth.lazada.com/rest/auth/token/create'),
        ],
        'tiktok_shop' => [
            'label' => 'TikTok Shop',
            'kind' => 'oauth',
            'color' => 'dark',
            'description' => 'Tarik pesanan dari toko TikTok Shop Anda.',
            'app_key' => env('TIKTOK_SHOP_APP_KEY'),
            'app_secret' => env('TIKTOK_SHOP_APP_SECRET'),
            'base_url' => env('TIKTOK_SHOP_BASE_URL', 'https://open-api.tiktokglobalshop.com'),
            'auth_url' => env('TIKTOK_SHOP_AUTH_URL', 'https://services.tiktokshop.com/open/authorize'),
        ],
    ],

    /*
     | URL callback OAuth. Harus didaftarkan persis seperti ini di dashboard partner.
     */
    'redirect_url' => env('MARKETPLACE_REDIRECT_URL', env('APP_URL').'/api/v1/marketplaces/callback'),

    /*
     | Ke mana pengguna dikembalikan setelah proses OAuth selesai.
     */
    'frontend_return_url' => env(
        'MARKETPLACE_RETURN_URL',
        rtrim((string) env('FRONTEND_URL', 'http://localhost:5174'), '/').'/settings/integrations',
    ),
];
