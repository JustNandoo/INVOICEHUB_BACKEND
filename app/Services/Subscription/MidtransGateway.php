<?php

namespace App\Services\Subscription;

use App\Exceptions\Subscription\PaymentException;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembungkus tipis di atas Midtrans Snap.
 *
 * Hanya kelas ini yang tahu bentuk permintaan dan tanda tangan Midtrans. Lapisan di
 * atasnya bekerja dengan model SubscriptionPayment saja, sehingga mengganti provider
 * kelak tidak menyentuh logika langganan.
 */
class MidtransGateway
{
    public const PROVIDER = 'midtrans';

    /** @return array<string, mixed> */
    private function config(): array
    {
        return (array) config('services.midtrans', []);
    }

    public function isConfigured(): bool
    {
        return filled($this->config()['server_key'] ?? null)
            && filled($this->config()['client_key'] ?? null);
    }

    public function isProduction(): bool
    {
        return (bool) ($this->config()['is_production'] ?? false);
    }

    public function clientKey(): string
    {
        return (string) ($this->config()['client_key'] ?? '');
    }

    /** Berkas Snap.js berbeda antara sandbox dan produksi. */
    public function snapJsUrl(): string
    {
        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    private function snapUrl(): string
    {
        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    private function apiUrl(): string
    {
        return $this->isProduction()
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    private function serverKey(): string
    {
        return (string) ($this->config()['server_key'] ?? '');
    }

    /**
     * Membuat transaksi Snap dan mengembalikan tokennya.
     *
     * @throws PaymentException
     */
    public function createSnapToken(SubscriptionPayment $payment, User $user): string
    {
        if (! $this->isConfigured()) {
            throw PaymentException::notConfigured();
        }

        $plan = $payment->plan;
        $minutes = max(5, (int) ($this->config()['expiry_minutes'] ?? 60));

        $body = [
            'transaction_details' => [
                'order_id' => $payment->order_id,
                'gross_amount' => $payment->amount,
            ],
            'item_details' => [[
                'id' => $plan->code,
                // Midtrans memotong nama item di 50 karakter.
                'name' => mb_substr('Langganan '.$plan->name.' 1 bulan', 0, 50),
                'price' => $payment->amount,
                'quantity' => 1,
            ]],
            'customer_details' => [
                'first_name' => mb_substr($user->name, 0, 50),
                'email' => $user->email,
                'phone' => $user->whatsapp,
            ],
            // Urutan larik ini menentukan urutan tampil di Snap. QRIS didahulukan
            // karena biayanya jauh paling murah bagi penerima.
            'enabled_payments' => (array) ($this->config()['enabled_payments'] ?? []),
            'expiry' => ['unit' => 'minute', 'duration' => $minutes],
            'callbacks' => ['finish' => (string) $this->config()['finish_url']],
        ];

        try {
            $response = Http::withBasicAuth($this->serverKey(), '')
                ->acceptJson()
                ->timeout(30)
                ->post($this->snapUrl(), $body);
        } catch (Throwable $exception) {
            throw PaymentException::providerRejected($exception->getMessage());
        }

        if ($response->failed()) {
            Log::warning('Midtrans menolak pembuatan transaksi', [
                'orderId' => $payment->order_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $errors = (array) $response->json('error_messages', []);

            throw PaymentException::providerRejected(
                $errors === [] ? 'HTTP '.$response->status() : implode(' ', $errors),
            );
        }

        $token = (string) $response->json('token', '');

        if ($token === '') {
            throw PaymentException::providerRejected('Token Snap tidak dikembalikan.');
        }

        return $token;
    }

    /**
     * Memverifikasi keaslian notifikasi.
     *
     * Inilah satu-satunya bukti bahwa sebuah notifikasi benar berasal dari Midtrans:
     * signature_key adalah SHA-512 dari order_id, status_code, gross_amount, dan
     * server key yang hanya diketahui server kita.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $expected = hash('sha512', implode('', [
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            $this->serverKey(),
        ]));

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    /**
     * Menanyakan status sebuah order langsung ke Midtrans.
     *
     * Dipakai sebagai jaring pengaman bila notifikasi tidak pernah sampai, misalnya
     * karena server sempat mati saat Midtrans mencoba mengirimkannya.
     *
     * @return array<string, mixed>
     */
    public function fetchStatus(string $orderId): array
    {
        if (! $this->isConfigured()) {
            throw PaymentException::notConfigured();
        }

        $response = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()
            ->timeout(30)
            ->get($this->apiUrl()."/{$orderId}/status");

        if ($response->failed()) {
            throw PaymentException::providerRejected('Status tidak dapat dibaca: HTTP '.$response->status());
        }

        return (array) $response->json();
    }
}
