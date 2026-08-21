<?php

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $scope,
        public readonly int $used,
        public readonly ?int $limit,
        public readonly ?string $resetAt = null,
    ) {
        parent::__construct($scope === 'daily'
            ? 'Kuota AI harian Anda sudah habis. Silakan coba lagi besok.'
            : 'Kuota AI bulan ini sudah habis. Tingkatkan paket untuk menambah kuota.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => [
                'code' => 'AI_QUOTA_EXCEEDED',
                'scope' => $this->scope,
                'used' => $this->used,
                'limit' => $this->limit,
                'resetAt' => $this->resetAt,
            ],
        ], 429);
    }
}
