<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class Sanitizer
{
    /**
     * Free-text coming from imported files, invoices or customers is untrusted input.
     * Collapse whitespace, strip control characters and cap the length so a crafted
     * description cannot flood the prompt.
     */
    public function text(?string $value, int $limit = 200): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[\p{C}]+/u', ' ', $value) ?? '';
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return $clean === '' ? null : Str::limit($clean, $limit, '');
    }

    /**
     * Never send a full account number; the API already exposes only the last four.
     */
    public function maskAccount(?string $lastFour): ?string
    {
        return $lastFour === null ? null : '****'.$lastFour;
    }

    /**
     * Personal names are needed for matching, but only the first few words carry
     * signal and long strings are a prompt-injection surface.
     */
    public function name(?string $value): ?string
    {
        return $this->text($value, 80);
    }

    /**
     * Drop keys that must never leave the backend.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withoutPii(array $payload): array
    {
        $forbidden = [
            'email', 'customer_email', 'customerEmail', 'whatsapp', 'customer_whatsapp',
            'customerWhatsapp', 'address', 'customer_address', 'customerAddress',
            'issuer_email', 'issuerEmail', 'issuer_address', 'issuerAddress',
            'account_number', 'accountNumber', 'password', 'token', 'api_key', 'apiKey',
        ];

        foreach ($payload as $key => $value) {
            if (in_array($key, $forbidden, true)) {
                unset($payload[$key]);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->withoutPii($value);
            }
        }

        return $payload;
    }
}
