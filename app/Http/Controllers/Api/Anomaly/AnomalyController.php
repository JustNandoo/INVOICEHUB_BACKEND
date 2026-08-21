<?php

namespace App\Http\Controllers\Api\Anomaly;

use App\Enums\Anomaly\AnomalyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Anomaly\ListAnomaliesRequest;
use App\Http\Requests\Api\Anomaly\ResolveAnomalyRequest;
use App\Http\Resources\Api\Anomaly\AnomalyResource;
use App\Models\FinancialAnomaly;
use App\Services\Anomaly\AnomalyDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnomalyController extends Controller
{
    public function __construct(private readonly AnomalyDetectionService $detection) {}

    public function index(ListAnomaliesRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $paginator = FinancialAnomaly::query()
            ->where('user_id', $request->user()->id)
            ->where('status', $filters['status'] ?? AnomalyStatus::Open->value)
            ->when($filters['severity'] ?? null, fn ($query, string $severity) => $query->where('severity', $severity))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('amount_at_risk')
            ->orderByDesc('id')
            ->paginate(perPage: (int) $filters['perPage'], page: (int) $filters['page'])
            ->withQueryString();

        return response()->json(['success' => true, 'data' => [
            'anomalies' => AnomalyResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'currentPage' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                'previousPageUrl' => $paginator->previousPageUrl(), 'nextPageUrl' => $paginator->nextPageUrl(),
            ],
        ]]);
    }

    /** Feeds the loss-monitor card: totals only, no AI involved. */
    public function summary(Request $request): JsonResponse
    {
        $base = FinancialAnomaly::query()->where('user_id', $request->user()->id);
        $open = (clone $base)->where('status', AnomalyStatus::Open->value);

        return response()->json(['success' => true, 'data' => ['summary' => [
            'openCount' => (clone $open)->count(),
            'criticalCount' => (clone $open)->where('severity', 'critical')->count(),
            'warningCount' => (clone $open)->where('severity', 'warning')->count(),
            'amountAtRisk' => (int) (clone $open)->sum('amount_at_risk'),
            'resolvedCount' => (clone $base)->where('status', AnomalyStatus::Resolved->value)->count(),
            'explainedCount' => (clone $open)->whereNotNull('explanation')->count(),
            'lastDetectedAt' => (clone $base)->max('detected_at'),
        ]]]);
    }

    public function scan(Request $request): JsonResponse
    {
        $result = $this->detection->scan($request->user());

        return response()->json([
            'success' => true,
            'message' => $result['detected'] === 0
                ? 'Pemindaian selesai. Tidak ada temuan baru.'
                : "Pemindaian selesai. {$result['detected']} temuan baru ditemukan.",
            'data' => ['scan' => $result],
        ]);
    }

    public function resolve(ResolveAnomalyRequest $request, int $anomaly): JsonResponse
    {
        $model = FinancialAnomaly::query()->where('user_id', $request->user()->id)->findOrFail($anomaly);
        $data = $request->validated();

        $model->update([
            'status' => $data['resolution'] === 'not_an_issue'
                ? AnomalyStatus::Dismissed->value
                : AnomalyStatus::Resolved->value,
            'resolution' => $data['resolution'],
            'notes' => $data['notes'] ?? null,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Temuan ditandai selesai.',
            'data' => ['anomaly' => (new AnomalyResource($model->refresh()))->resolve($request)],
        ]);
    }
}
