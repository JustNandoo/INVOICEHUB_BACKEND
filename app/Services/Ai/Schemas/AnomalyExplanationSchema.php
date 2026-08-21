<?php

namespace App\Services\Ai\Schemas;

use App\Enums\Anomaly\AnomalyAction;

class AnomalyExplanationSchema
{
    public const MAX_EXPLANATION_LENGTH = 600;

    public const MAX_CAUSE_LENGTH = 300;

    public const MAX_TIP_LENGTH = 300;

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'explanation' => [
                    'type' => 'string',
                    'description' => 'Penjelasan temuan dalam Bahasa Indonesia sederhana, 2-3 kalimat.',
                ],
                'likelyCause' => [
                    'type' => 'string',
                    'description' => 'Dugaan penyebab paling masuk akal, satu kalimat.',
                ],
                'preventionTip' => [
                    'type' => 'string',
                    'description' => 'Satu saran praktis agar tidak terulang.',
                ],
                'recommendedAction' => [
                    'type' => 'string',
                    'enum' => AnomalyAction::values(),
                ],
                'confidence' => [
                    'type' => 'integer',
                    'description' => 'Keyakinan 0-100 terhadap dugaan penyebab.',
                ],
            ],
            'required' => ['explanation', 'likelyCause', 'preventionTip', 'recommendedAction', 'confidence'],
        ];
    }
}
