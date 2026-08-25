<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Enums\Marketplace\MarketplacePlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Marketplace\ConnectMarketplaceRequest;
use App\Http\Resources\Api\Marketplace\MarketplaceConnectionResource;
use App\Models\MarketplaceConnection;
use App\Services\Marketplace\MarketplaceConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceConnectionController extends Controller
{
    public function __construct(private readonly MarketplaceConnectionService $connections) {}

    /** Katalog platform beserta status sambungan pengguna. */
    public function index(Request $request): JsonResponse
    {
        $catalog = collect($this->connections->catalog($request->user()))
            ->map(fn (array $entry): array => [
                ...$entry,
                'connection' => $entry['connection'] === null
                    ? null
                    : (new MarketplaceConnectionResource($entry['connection']))->resolve($request),
            ])
            ->all();

        return response()->json(['success' => true, 'data' => ['platforms' => $catalog]]);
    }

    /**
     * Memulai penyambungan. Untuk platform OAuth, balasan berisi authorizationUrl
     * yang harus dibuka pengguna di jendela marketplace.
     */
    public function store(ConnectMarketplaceRequest $request): JsonResponse
    {
        $platform = MarketplacePlatform::from($request->validated('platform'));
        $result = $this->connections->begin($request->user(), $platform, $request->validated('shopId'));

        return response()->json([
            'success' => true,
            'message' => $result['authorizationUrl'] === null
                ? 'Sambungan siap dipakai.'
                : 'Buka tautan otorisasi untuk menyelesaikan penyambungan.',
            'data' => [
                'connection' => (new MarketplaceConnectionResource($result['connection']))->resolve($request),
                'authorizationUrl' => $result['authorizationUrl'],
            ],
        ], 201);
    }

    public function destroy(Request $request, int $connection): JsonResponse
    {
        $model = MarketplaceConnection::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($connection);

        return response()->json([
            'success' => true,
            'message' => 'Sambungan diputus. Data yang sudah terimpor tetap tersimpan.',
            'data' => [
                'connection' => (new MarketplaceConnectionResource($this->connections->disconnect($model)))->resolve($request),
            ],
        ]);
    }
}
