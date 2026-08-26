<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SubscriptionPaymentResource;
use App\Http\Resources\Api\SubscriptionPlanResource;
use App\Http\Resources\Api\UserSubscriptionResource;
use App\Services\Subscription\EntitlementService;
use App\Services\Subscription\MidtransGateway;
use App\Services\Subscription\PlanCatalogService;
use App\Services\Subscription\SubscriptionCheckoutService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanCatalogService $plans,
        private readonly SubscriptionService $subscriptions,
        private readonly EntitlementService $entitlements,
        private readonly SubscriptionCheckoutService $checkout,
        private readonly MidtransGateway $gateway,
    ) {}

    public function plans(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'plans' => SubscriptionPlanResource::collection($this->plans->all())->resolve($request),
        ]]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'subscription' => (new UserSubscriptionResource(
                $this->subscriptions->current($request->user()),
            ))->resolve($request),
            'usage' => $this->entitlements->usage($request->user()),
            'paymentHistory' => SubscriptionPaymentResource::collection(
                $this->checkout->history($request->user()),
            )->resolve($request),
            'paymentsAvailable' => $this->gateway->isConfigured(),
        ]]);
    }

    public function usage(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'usage' => $this->entitlements->usage($request->user()),
        ]]);
    }

    public function paymentHistory(Request $request): JsonResponse
    {
        $available = $this->gateway->isConfigured();

        return response()->json([
            'success' => true,
            'message' => $available
                ? 'Riwayat pembayaran berhasil dimuat.'
                : 'Riwayat pembayaran akan tersedia setelah sistem pembayaran diaktifkan.',
            'data' => [
                'payments' => SubscriptionPaymentResource::collection(
                    $this->checkout->history($request->user()),
                )->resolve($request),
                'paymentsAvailable' => $available,
            ],
        ]);
    }
}
