<?php

namespace App\Http\Controllers\Api\Reconciliation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reconciliation\ConfirmReconciliationRequest;
use App\Http\Requests\Api\Reconciliation\ListReconciliationsRequest;
use App\Http\Requests\Api\Reconciliation\ReverseReconciliationRequest;
use App\Http\Resources\Api\ReconciliationResource;
use App\Models\Reconciliation;
use App\Services\Reconciliation\ReconciliationQueryService;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function summary(Request $request, ReconciliationQueryService $queries): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'summary' => $queries->summary($request->user()),
        ]]);
    }

    public function index(ListReconciliationsRequest $request, ReconciliationQueryService $queries): JsonResponse
    {
        $paginator = $queries->reconciliations($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => [
            'reconciliations' => ReconciliationResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'currentPage' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                'previousPageUrl' => $paginator->previousPageUrl(), 'nextPageUrl' => $paginator->nextPageUrl(),
            ],
        ]]);
    }

    public function store(ConfirmReconciliationRequest $request, ReconciliationService $service): JsonResponse
    {
        $reconciliation = $service->confirm($request->user(), $request->validated());

        return response()->json([
            'success' => true, 'message' => 'Mutasi dan invoice berhasil direkonsiliasi.',
            'data' => ['reconciliation' => (new ReconciliationResource($reconciliation))->resolve($request)],
        ], 201);
    }

    public function reverse(ReverseReconciliationRequest $request, int $reconciliation, ReconciliationService $service): JsonResponse
    {
        $model = Reconciliation::query()->where('user_id', $request->user()->id)->findOrFail($reconciliation);
        $result = $service->reverse($model, $request->user(), $request->validated('reason'));

        return response()->json([
            'success' => true, 'message' => 'Rekonsiliasi berhasil dibatalkan dan saldo invoice dikembalikan.',
            'data' => ['reconciliation' => (new ReconciliationResource($result))->resolve($request)],
        ]);
    }
}
