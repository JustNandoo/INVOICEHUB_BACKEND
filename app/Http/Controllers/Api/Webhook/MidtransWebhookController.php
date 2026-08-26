<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Titik masuk notifikasi Midtrans.
 *
 * Tanpa Sanctum karena yang memanggil adalah server Midtrans, bukan browser
 * pengguna. Keabsahannya dijamin signature_key yang hanya bisa dihitung oleh pihak
 * yang tahu server key kita.
 *
 * Inilah satu-satunya jalur yang boleh mengaktifkan paket berbayar.
 */
class MidtransWebhookController extends Controller
{
    public function __construct(private readonly SubscriptionCheckoutService $checkout) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payment = $this->checkout->handleNotification($request->all());

        // Midtrans mengirim ulang notifikasi sampai menerima 200.
        return response()->json([
            'success' => true,
            'data' => ['orderId' => $payment->order_id, 'status' => $payment->status],
        ]);
    }
}
