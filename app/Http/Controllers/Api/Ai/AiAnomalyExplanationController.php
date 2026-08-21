<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Anomaly\AnomalyResource;
use App\Models\FinancialAnomaly;
use App\Services\Ai\Features\AnomalyExplanationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAnomalyExplanationController extends Controller
{
    public function store(Request $request, int $anomaly, AnomalyExplanationService $explanations): JsonResponse
    {
        $model = FinancialAnomaly::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($anomaly);

        $result = $explanations->explain(
            $request->user(),
            $model,
            force: $request->boolean('force'),
        );

        return response()->json([
            'success' => true,
            'message' => $result['aiAvailable']
                ? 'Penjelasan AI siap. Temuan ini tetap perlu Anda periksa sendiri.'
                : 'AI sedang tidak tersedia. Menampilkan hasil deteksi aturan.',
            'data' => [
                'anomaly' => (new AnomalyResource($model->refresh()))->resolve($request),
                'explanation' => $result,
            ],
        ]);
    }
}
