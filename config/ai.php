<?php

return [
    'enabled' => (bool) env('AI_ENABLED', false),

    'provider' => env('AI_PROVIDER', 'gemini'),

    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),

    'max_retries' => (int) env('AI_MAX_RETRIES', 2),

    'retention_days' => (int) env('AI_RETENTION_DAYS', 180),

    'usd_to_idr' => (int) env('AI_USD_TO_IDR', 16500),

    'daily_cost_limit_idr' => (int) env('AI_DAILY_COST_LIMIT_IDR', 100000),

    'queue' => env('AI_QUEUE', 'ai'),

    'providers' => [
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'models' => [
                'reasoning' => env('GEMINI_MODEL_REASONING', 'gemini-3.6-flash'),
                'fast' => env('GEMINI_MODEL_FAST', 'gemini-3.5-flash-lite'),
            ],
        ],
    ],

    /*
     | USD per one million tokens, keyed by model name. The Gemini free tier bills
     | nothing, so the defaults are zero. Fill these in before moving to a paid tier
     | so ai_runs.estimated_cost stays meaningful.
     */
    'pricing' => [
        'gemini-3.6-flash' => ['input' => 0.0, 'output' => 0.0],
        'gemini-3.5-flash-lite' => ['input' => 0.0, 'output' => 0.0],
    ],

    /*
     | Per-feature routing. thinking_budget 0 means the key is not sent at all, which
     | is required because Gemini 3.x rejects an explicit budget of zero. Note that
     | thinking tokens are charged against max_output_tokens, so keep it generous.
     |
     | Per-feature routing. "model" points at a key inside providers.*.models so the
     | feature services never name a concrete model. Credit weights are provisional
     | and should be revisited once real token usage has been measured.
     */
    'features' => [
        'reconciliation_analysis' => [
            'model' => 'reasoning',
            'credits' => 4,
            'max_output_tokens' => 3000,
            'thinking_budget' => 0,
            'entitlement' => 'ai.reconciliation',
            'prompt_version' => 'v1',
            'cache_minutes' => 60,
        ],
        'financial_insight' => [
            'model' => 'reasoning',
            'credits' => 3,
            'max_output_tokens' => 4000,
            'thinking_budget' => 0,
            'entitlement' => 'ai.insights',
            'prompt_version' => 'v1',
            'cache_minutes' => 1440,
        ],
        'anomaly_explanation' => [
            'model' => 'reasoning',
            'credits' => 4,
            'max_output_tokens' => 3000,
            'thinking_budget' => 0,
            'entitlement' => 'ai.anomaly_explanation',
            'prompt_version' => 'v1',
            'cache_minutes' => 10080,
        ],
        'payment_reminder_draft' => [
            'model' => 'fast',
            'credits' => 1,
            'max_output_tokens' => 2000,
            'thinking_budget' => 0,
            'entitlement' => 'ai.reminder_draft',
            'prompt_version' => 'v1',
            'cache_minutes' => 0,
        ],
    ],
];
