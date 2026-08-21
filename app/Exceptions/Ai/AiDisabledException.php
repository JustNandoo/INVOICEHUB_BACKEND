<?php

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiDisabledException extends RuntimeException
{
    public function __construct(public readonly string $reason = 'disabled')
    {
        parent::__construct('Fitur AI sedang tidak tersedia. Silakan coba lagi nanti.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => [
                'code' => 'AI_UNAVAILABLE',
                'reason' => $this->reason,
            ],
        ], 503);
    }
}
