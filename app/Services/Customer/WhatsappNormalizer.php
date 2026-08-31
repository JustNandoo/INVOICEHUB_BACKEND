<?php

namespace App\Services\Customer;

use Illuminate\Validation\ValidationException;

class WhatsappNormalizer
{
    /** @return array{display: string, normalized: string} */
    public function normalize(string $value): array
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if (! $digits) {
            throw ValidationException::withMessages(['whatsapp' => ['Masukkan nomor WhatsApp yang valid.']]);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (! preg_match('/^[1-9][0-9]{9,14}$/', $digits)) {
            throw ValidationException::withMessages(['whatsapp' => ['WhatsApp number must contain 10 to 15 digits including the country code.']]);
        }

        return ['display' => '+'.$digits, 'normalized' => $digits];
    }
}
