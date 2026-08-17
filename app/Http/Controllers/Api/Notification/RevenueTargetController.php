<?php

namespace App\Http\Controllers\Api\Notification;

use App\Events\Report\RevenueTargetReached;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\StoreRevenueTargetRequest;
use App\Services\Notification\RevenueTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueTargetController extends Controller
{
    public function __construct(private readonly RevenueTargetService $targets) {}

    public function show(Request $request, int $year, int $month): JsonResponse
    {
        $this->guardPeriod($year, $month);

        return response()->json([
            'success' => true,
            'data' => $this->resource($this->targets->summary($request->user(), $year, $month), $year, $month),
        ]);
    }

    public function update(StoreRevenueTargetRequest $request, int $year, int $month): JsonResponse
    {
        $this->guardPeriod($year, $month);
        $this->targets->set($request->user(), $year, $month, (int) $request->validated('amount'));
        $evaluation = $this->targets->evaluate($request->user(), $year, $month);
        if ($evaluation['newlyReached'] && $evaluation['target']) {
            RevenueTargetReached::dispatch($evaluation['target']->id, $evaluation['currentRevenue']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Monthly revenue target saved.',
            'data' => $this->resource($this->targets->summary($request->user(), $year, $month), $year, $month),
        ]);
    }

    /** @param array{target: mixed, currentRevenue: int, progressPercent: float, isReached: bool} $summary */
    private function resource(array $summary, int $year, int $month): array
    {
        return [
            'target' => [
                'id' => $summary['target']?->id,
                'year' => $year,
                'month' => $month,
                'amount' => $summary['target']?->amount,
                'reachedAt' => $summary['target']?->reached_at?->toIso8601String(),
            ],
            'currentRevenue' => $summary['currentRevenue'],
            'progressPercent' => $summary['progressPercent'],
            'isReached' => $summary['isReached'],
        ];
    }

    private function guardPeriod(int $year, int $month): void
    {
        abort_unless($year >= 2020 && $year <= 2100 && $month >= 1 && $month <= 12, 404);
    }
}
