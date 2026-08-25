<?php

namespace App\Exceptions\Marketplace;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class MarketplaceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'MARKETPLACE_ERROR',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(string $platform): self
    {
        return new self(
            "Integrasi {$platform} belum dikonfigurasi. Kredensial partner harus diisi lebih dulu.",
            'MARKETPLACE_NOT_CONFIGURED',
            503,
        );
    }

    public static function unsupported(string $platform, string $action): self
    {
        return new self(
            "Platform {$platform} tidak mendukung {$action}.",
            'MARKETPLACE_UNSUPPORTED',
            422,
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
