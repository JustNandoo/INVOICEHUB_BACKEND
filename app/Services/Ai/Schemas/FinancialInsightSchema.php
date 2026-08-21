<?php

namespace App\Services\Ai\Schemas;

use App\Enums\Ai\InsightAction;
use App\Enums\Ai\InsightSeverity;
use App\Enums\Ai\InsightType;

class FinancialInsightSchema
{
    public const MAX_TITLE_LENGTH = 150;

    public const MAX_SUMMARY_LENGTH = 500;

    public const MAX_EVIDENCE = 4;

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'insights' => [
                    'type' => 'array',
                    'description' => 'Insight terurut dari yang paling mendesak.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => InsightType::values()],
                            'severity' => ['type' => 'string', 'enum' => InsightSeverity::values()],
                            'title' => ['type' => 'string', 'description' => 'Judul singkat Bahasa Indonesia.'],
                            'summary' => ['type' => 'string', 'description' => 'Penjelasan 1-2 kalimat.'],
                            'evidence' => [
                                'type' => 'array',
                                'description' => 'Angka pendukung. Nilai WAJIB disalin persis dari blok metrics.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'label' => ['type' => 'string'],
                                        'value' => ['type' => 'integer'],
                                    ],
                                    'required' => ['label', 'value'],
                                ],
                            ],
                            'recommendedAction' => ['type' => 'string', 'enum' => InsightAction::values()],
                        ],
                        'required' => ['type', 'severity', 'title', 'summary', 'evidence', 'recommendedAction'],
                    ],
                ],
            ],
            'required' => ['insights'],
        ];
    }
}
