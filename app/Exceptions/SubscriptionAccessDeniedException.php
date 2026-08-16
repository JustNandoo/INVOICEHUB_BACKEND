<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class SubscriptionAccessDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        public readonly string $currentPlan,
        public readonly ?string $limit = null,
    ) {
        parent::__construct('Fitur ini tidak tersedia pada paket langganan Anda saat ini.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => [
                'code' => 'PLAN_UPGRADE_REQUIRED',
                'requiredFeature' => $this->feature,
                'currentPlan' => $this->currentPlan,
                'limit' => $this->limit,
            ],
        ], 403);
    }
}
