<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Ai\AiInsightResource;
use App\Jobs\Ai\GenerateFinancialInsights;
use App\Models\AiInsight;
use App\Services\Ai\Features\FinancialInsightService;
use App\Services\Subscription\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiInsightController extends Controller
{
    /** Refreshing on demand more often than this is refused, so a busy dashboard cannot bill. */
    private const REFRESH_COOLDOWN_MINUTES = 60;

    public function __construct(
        private readonly FinancialInsightService $insights,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Reads stored insights only. This endpoint never calls the AI provider.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $lastGeneratedAt = $this->insights->lastGeneratedAt($user);

        return response()->json([
            'success' => true,
            'data' => [
                'insights' => AiInsightResource::collection($this->insights->visible($user))->resolve($request),
                'meta' => [
                    'enabled' => (bool) config('ai.enabled'),
                    'maxInsights' => $this->insights->maxInsights($user),
                    'lastGeneratedAt' => $lastGeneratedAt?->toIso8601String(),
                    'canRefreshOnDemand' => $this->entitlements->has($user, 'ai.on_demand_refresh'),
                    'nextRefreshAvailableAt' => $lastGeneratedAt?->addMinutes(self::REFRESH_COOLDOWN_MINUTES)->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Queues a regeneration. The response returns immediately; the dashboard polls index.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $lastGeneratedAt = $this->insights->lastGeneratedAt($user);

        if ($lastGeneratedAt !== null && $lastGeneratedAt->gt(now()->subMinutes(self::REFRESH_COOLDOWN_MINUTES))) {
            throw ValidationException::withMessages([
                'refresh' => ['Insight baru saja diperbarui. Silakan coba lagi nanti.'],
            ]);
        }

        GenerateFinancialInsights::dispatch($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Analisis keuangan sedang diproses. Insight akan muncul sebentar lagi.',
            'data' => ['queued' => true],
        ], 202);
    }

    public function destroy(Request $request, int $insight): JsonResponse
    {
        $model = AiInsight::query()->where('user_id', $request->user()->id)->findOrFail($insight);
        $model->update(['dismissed_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Insight disembunyikan.',
            'data' => ['insight' => (new AiInsightResource($model->refresh()))->resolve($request)],
        ]);
    }
}
