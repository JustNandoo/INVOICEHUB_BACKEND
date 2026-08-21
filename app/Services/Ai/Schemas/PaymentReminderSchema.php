<?php

namespace App\Services\Ai\Schemas;

class PaymentReminderSchema
{
    public const TONES = ['polite', 'neutral', 'firm'];

    /** Mirrors the `message` rule on SendInvoiceRequest so a draft can always be sent as-is. */
    public const MAX_MESSAGE_LENGTH = 1000;

    public const MAX_SUBJECT_LENGTH = 150;

    public const MAX_DRAFTS = 3;

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'drafts' => [
                    'type' => 'array',
                    'description' => 'Satu draft per nada yang diminta.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'tone' => ['type' => 'string', 'enum' => self::TONES],
                            'subject' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Subjek email. Kosongkan untuk WhatsApp.',
                            ],
                            'message' => [
                                'type' => 'string',
                                'description' => 'Isi pesan, maksimal 1000 karakter, Bahasa Indonesia.',
                            ],
                        ],
                        'required' => ['tone', 'message'],
                    ],
                ],
            ],
            'required' => ['drafts'],
        ];
    }
}
