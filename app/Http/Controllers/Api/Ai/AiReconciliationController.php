<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReconciliationSuggestionResource;
use App\Models\BankTransaction;
use App\Services\Ai\Features\ReconciliationAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiReconciliationController extends Controller
{
    public function analyze(Request $request, int $bankTransaction, ReconciliationAiService $service): JsonResponse
    {
        $transaction = BankTransaction::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($bankTransaction);

        $result = $service->analyze($request->user(), $transaction);

        return response()->json([
            'success' => true,
            'message' => $result['analysis']['aiAvailable']
                ? 'Analisis AI selesai. Konfirmasi Anda tetap diperlukan.'
                : 'AI sedang tidak tersedia. Menampilkan kandidat berbasis aturan.',
            'data' => [
                'transactionId' => $transaction->id,
                'candidates' => ReconciliationSuggestionResource::collection($result['candidates'])->resolve($request),
                'analysis' => $result['analysis'],
            ],
        ]);
    }
}
