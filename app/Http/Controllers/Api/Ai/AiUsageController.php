<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiUsageController extends Controller
{
    public function show(Request $request, AiCreditService $credits): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => (bool) config('ai.enabled'),
                'credits' => $credits->usage($request->user()),
            ],
        ]);
    }
}
