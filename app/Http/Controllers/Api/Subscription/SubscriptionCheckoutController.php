<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Subscription\CheckoutSubscriptionRequest;
use App\Services\Subscription\MidtransGateway;
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;

class SubscriptionCheckoutController extends Controller
{
    public function __construct(
        private readonly SubscriptionCheckoutService $checkout,
        private readonly MidtransGateway $gateway,
    ) {}

    /**
     * Menyiapkan transaksi Snap. Belum mengubah paket apa pun — paket baru aktif
     * setelah notifikasi Midtrans yang sah diterima di webhook.
     */
    public function store(CheckoutSubscriptionRequest $request): JsonResponse
    {
        $payment = $this->checkout->checkout($request->user(), $request->validated('planCode'));

        return response()->json(['success' => true, 'data' => [
            'checkout' => [
                'orderId' => $payment->order_id,
                'snapToken' => $payment->snap_token,
                'amount' => $payment->amount,
                'planName' => $payment->plan->name,
                'expiresAt' => $payment->expires_at?->toIso8601String(),
                // Dikirim dari server supaya frontend tidak perlu konfigurasi sendiri
                // dan tidak pernah salah pasangan antara sandbox dan produksi.
                'clientKey' => $this->gateway->clientKey(),
                'snapJsUrl' => $this->gateway->snapJsUrl(),
                'isProduction' => $this->gateway->isProduction(),
            ],
        ]], 201);
    }
}
