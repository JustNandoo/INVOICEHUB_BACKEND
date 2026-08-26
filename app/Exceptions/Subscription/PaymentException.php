<?php

namespace App\Exceptions\Subscription;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'PAYMENT_FAILED',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(
            'Pembayaran belum aktif. Kredensial Midtrans belum diisi di server.',
            'PAYMENT_NOT_CONFIGURED',
            503,
        );
    }

    public static function providerRejected(string $detail): self
    {
        return new self(
            'Midtrans menolak permintaan pembayaran: '.$detail,
            'PAYMENT_PROVIDER_ERROR',
            502,
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => ['code' => $this->errorCode],
        ], $this->status);
    }
}
