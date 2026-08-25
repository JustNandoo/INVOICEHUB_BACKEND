<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Marketplace\ImportMarketplaceOrdersRequest;
use App\Http\Requests\Api\Marketplace\ListMarketplaceOrdersRequest;
use App\Http\Resources\Api\Marketplace\MarketplaceOrderResource;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceOrder;
use App\Services\Marketplace\MarketplaceOrderImporter;
use App\Services\Marketplace\MarketplaceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceOrderController extends Controller
{
    public function __construct(
        private readonly MarketplaceSyncService $sync,
        private readonly MarketplaceOrderImporter $importer,
    ) {}

    public function index(ListMarketplaceOrdersRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $paginator = MarketplaceOrder::query()
            ->where('user_id', $request->user()->id)
            ->with('connection')
            ->when($filters['syncStatus'] ?? null, fn ($query, string $status) => $query->where('sync_status', $status))
            ->when($filters['platform'] ?? null, fn ($query, string $platform) => $query->whereHas(
                'connection',
                fn ($inner) => $inner->where('platform', $platform),
            ))
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(perPage: (int) $filters['perPage'], page: (int) $filters['page'])
            ->withQueryString();

        return response()->json(['success' => true, 'data' => [
            'orders' => MarketplaceOrderResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'currentPage' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                'previousPageUrl' => $paginator->previousPageUrl(), 'nextPageUrl' => $paginator->nextPageUrl(),
            ],
        ]]);
    }

    /** Menarik pesanan terbaru dari platform lalu memetakannya. */
    public function sync(Request $request, int $connection): JsonResponse
    {
        $model = $this->ownedConnection($request, $connection);
        $result = $this->sync->sync($model);

        return response()->json([
            'success' => true,
            'message' => sprintf(
                '%d pesanan baru diambil, %d menjadi invoice.',
                $result['stored'],
                $result['imported'],
            ),
            'data' => ['sync' => $result],
        ]);
    }

    /** Mengunggah ekspor pesanan dari Seller Center. */
    public function import(ImportMarketplaceOrdersRequest $request, int $connection): JsonResponse
    {
        $model = $this->ownedConnection($request, $connection);
        $parsed = $this->importer->parse($model, $request->file('file'));

        $stored = $this->sync->storeOrders($model, $parsed['orders']);
        $mapped = $this->sync->mapPendingOrders($model);

        $model->update([
            'last_synced_at' => now(),
            'imported_order_count' => $model->orders()->where('sync_status', MarketplaceOrder::SYNC_IMPORTED)->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => sprintf('%d pesanan dibaca, %d menjadi invoice.', $stored, $mapped['imported']),
            'data' => ['import' => [
                'parsed' => count($parsed['orders']),
                'stored' => $stored,
                ...$mapped,
                'errors' => $parsed['errors'],
            ]],
        ], 201);
    }

    private function ownedConnection(Request $request, int $id): MarketplaceConnection
    {
        return MarketplaceConnection::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }
}
