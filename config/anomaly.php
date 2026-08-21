<?php

return [
    'enabled' => (bool) env('ANOMALY_DETECTION_ENABLED', true),

    /*
     | How far back a scan looks. Older records are considered settled history.
     */
    'lookback_days' => (int) env('ANOMALY_LOOKBACK_DAYS', 90),

    /*
     | Deterministic detection rules. These thresholds are deliberately configurable:
     | every business has a different idea of what counts as an unusual fee or discount,
     | so they should be calibrated against real data rather than guessed once in code.
     */
    'rules' => [
        'unmatched_incoming' => [
            'enabled' => true,
            'severity' => 'warning',
            'min_age_days' => 7,
            'min_amount' => 50_000,
        ],
        'excessive_fee' => [
            'enabled' => true,
            'severity' => 'warning',
            'max_percent' => 5,
            'max_amount' => 25_000,
        ],
        'excessive_discount' => [
            'enabled' => true,
            'severity' => 'warning',
            'max_percent' => 20,
            'min_amount' => 50_000,
        ],
        'unsettled_balance' => [
            'enabled' => true,
            'severity' => 'warning',
            'min_age_days' => 14,
            'min_amount' => 10_000,
        ],
        'duplicate_payment' => [
            'enabled' => true,
            'severity' => 'critical',
            'window_hours' => 48,
        ],
        'repeated_reversal' => [
            'enabled' => true,
            'severity' => 'critical',
            'min_count' => 2,
        ],
    ],
];
