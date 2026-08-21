<?php

namespace App\Services\Ai\Schemas;

class ReconciliationSchema
{
    public const ACTIONS = [
        'confirm_exact',
        'confirm_with_fee',
        'confirm_partial',
        'review_manually',
        'not_a_payment',
    ];

    public const MAX_REASONS = 5;

    public const MAX_REASON_LENGTH = 200;

    /**
     * Provider-neutral schema; each provider translates it into its own dialect.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'recommendedInvoiceId' => [
                    'type' => 'integer',
                    'nullable' => true,
                    'description' => 'ID invoice pilihan. WAJIB salah satu dari daftar kandidat, atau null bila tidak ada yang cocok.',
                ],
                'confidence' => [
                    'type' => 'integer',
                    'description' => 'Keyakinan 0-100.',
                ],
                'reasons' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Maksimal 5 alasan singkat berbahasa Indonesia.',
                ],
                'inferredFee' => [
                    'type' => 'integer',
                    'nullable' => true,
                    'description' => 'Dugaan biaya admin dalam rupiah, atau null.',
                ],
                'recommendedAction' => [
                    'type' => 'string',
                    'enum' => self::ACTIONS,
                ],
                'requiresReview' => [
                    'type' => 'boolean',
                ],
            ],
            'required' => ['recommendedInvoiceId', 'confidence', 'reasons', 'recommendedAction', 'requiresReview'],
        ];
    }
}
