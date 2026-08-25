<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Exceptions\Marketplace\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Titik pendaratan OAuth. Dipanggil oleh marketplace, bukan oleh frontend, jadi
 * endpoint ini tidak memakai Sanctum: keabsahannya dijamin oleh state token
 * sekali pakai yang dibuat saat penyambungan dimulai.
 */
class MarketplaceCallbackController extends Controller
{
    public function __invoke(Request $request, MarketplaceConnectionService $connections): RedirectResponse
    {
        $return = rtrim((string) config('marketplaces.frontend_return_url'), '/');
        $state = (string) $request->query('state', '');

        try {
            $connection = $connections->complete($state, $request->query());
        } catch (MarketplaceException $exception) {
            return redirect()->away($return.'?marketplace_error='.urlencode($exception->getMessage()));
        }

        return redirect()->away($return.'?marketplace_connected='.$connection->platform->value);
    }
}
