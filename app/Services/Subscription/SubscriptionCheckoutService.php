<?php

namespace App\Services\Subscription;

use App\Exceptions\Subscription\PaymentException;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Menyambungkan Midtrans dengan langganan pengguna.
 *
 * Aturan yang dipegang kelas ini: paket HANYA diaktifkan dari notifikasi Midtrans yang
 * tanda tangannya sah. Callback di browser tidak pernah dipercaya, karena siapa pun
 * bisa memanggilnya sendiri. Nominal juga selalu dibaca dari katalog paket di server,
 * tidak pernah dari permintaan klien.
 */
class SubscriptionCheckoutService
{
    public function __construct(
        private readonly MidtransGateway $gateway,
        private readonly PlanCatalogService $plans,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Menyiapkan pembayaran untuk sebuah paket dan mengembalikan transaksi Snap.
     *
     * @throws PaymentException
     */
    public function checkout(User $user, string $planCode): SubscriptionPayment
    {
        if (! $this->gateway->isConfigured()) {
            throw PaymentException::notConfigured();
        }

        $plan = $this->plans->find($planCode);

        if ($plan->price <= 0) {
            throw new PaymentException(
                'Paket gratis tidak perlu dibayar.',
                'PAYMENT_PLAN_NOT_PURCHASABLE',
            );
        }

        // Pengguna yang menutup popup lalu kembali memakai transaksi yang sama,
        // supaya tidak menumpuk order menganggur di dashboard Midtrans.
        $reusable = $this->reusablePayment($user, $plan->id);

        if ($reusable !== null) {
            return $reusable;
        }

        $payment = SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'order_id' => $this->newOrderId(),
            'status' => SubscriptionPayment::STATUS_PENDING,
            // Nominal diambil dari katalog server, bukan dari klien.
            'amount' => $plan->price,
            'provider' => MidtransGateway::PROVIDER,
            'expires_at' => now()->addMinutes(max(5, (int) config('services.midtrans.expiry_minutes', 60))),
        ]);
        $payment->setRelation('plan', $plan);

        $payment->update(['snap_token' => $this->gateway->createSnapToken($payment, $user)]);

        return $payment->refresh()->load('plan');
    }

    /**
     * Memproses notifikasi Midtrans.
     *
     * Aman dipanggil berkali-kali untuk order yang sama: Midtrans memang mengirim
     * ulang notifikasi sampai menerima balasan 200.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PaymentException
     */
    public function handleNotification(array $payload): SubscriptionPayment
    {
        if (! $this->gateway->verifySignature($payload)) {
            Log::warning('Notifikasi Midtrans dengan tanda tangan tidak sah ditolak', [
                'orderId' => $payload['order_id'] ?? null,
            ]);

            throw new PaymentException('Tanda tangan notifikasi tidak sah.', 'PAYMENT_INVALID_SIGNATURE', 403);
        }

        return DB::transaction(function () use ($payload): SubscriptionPayment {
            $payment = SubscriptionPayment::query()
                ->where('order_id', (string) ($payload['order_id'] ?? ''))
                ->lockForUpdate()
                ->firstOrFail();

            // Notifikasi susulan untuk pembayaran yang sudah lunas tidak boleh
            // mengaktifkan paket untuk kedua kalinya.
            if ($payment->isSettled() && $this->resolveStatus($payload) === SubscriptionPayment::STATUS_PAID) {
                return $payment;
            }

            // Tanda tangan sudah mencakup nominal, tetapi pencocokan ulang ini
            // menjaga agar order yang nominalnya berubah tidak pernah lolos.
            $notified = (int) round((float) ($payload['gross_amount'] ?? 0));

            if ($notified !== $payment->amount) {
                Log::warning('Nominal notifikasi Midtrans tidak cocok dengan catatan', [
                    'orderId' => $payment->order_id,
                    'expected' => $payment->amount,
                    'notified' => $notified,
                ]);

                throw new PaymentException('Nominal pembayaran tidak cocok.', 'PAYMENT_AMOUNT_MISMATCH', 422);
            }

            $status = $this->resolveStatus($payload);

            $payment->update([
                'status' => $status,
                'provider_reference' => $payload['transaction_id'] ?? $payment->provider_reference,
                'payment_type' => $payload['payment_type'] ?? $payment->payment_type,
                'paid_at' => $status === SubscriptionPayment::STATUS_PAID
                    ? ($payload['settlement_time'] ?? $payload['transaction_time'] ?? now())
                    : null,
                'failure_reason' => in_array($status, [
                    SubscriptionPayment::STATUS_FAILED,
                    SubscriptionPayment::STATUS_EXPIRED,
                ], true)
                    ? mb_substr((string) ($payload['status_message'] ?? $payload['transaction_status'] ?? ''), 0, 255)
                    : null,
                'raw_payload' => $payload,
            ]);

            if ($status === SubscriptionPayment::STATUS_PAID) {
                $this->activate($payment->refresh());
            }

            return $payment->refresh()->load('plan');
        }, 3);
    }

    /** @return Collection<int, SubscriptionPayment> */
    public function history(User $user): Collection
    {
        return SubscriptionPayment::query()
            ->with('plan')
            ->where('user_id', $user->id)
            // Order yang tidak pernah dibayar hanya menjadi kebisingan di riwayat.
            ->whereNot('status', SubscriptionPayment::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** Mengaktifkan paket sesudah pembayaran benar-benar lunas. */
    private function activate(SubscriptionPayment $payment): void
    {
        $this->subscriptions->activatePlan(
            $payment->user,
            $payment->plan->code,
            MidtransGateway::PROVIDER,
            $this->periodEndsAt($payment),
            [
                'orderId' => $payment->order_id,
                'paymentType' => $payment->payment_type,
                'transactionId' => $payment->provider_reference,
                'amount' => $payment->amount,
            ],
        );
    }

    /**
     * Perpanjangan sebelum masa aktif habis menyambung dari tanggal berakhir yang
     * lama, sehingga sisa hari yang sudah dibayar tidak hangus.
     */
    private function periodEndsAt(SubscriptionPayment $payment): ?CarbonImmutable
    {
        $current = UserSubscription::query()
            ->with('plan')
            ->where('user_id', $payment->user_id)
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '>', now())
            ->latest('starts_at')
            ->first();

        if ($current === null || $current->subscription_plan_id !== $payment->subscription_plan_id) {
            return null;
        }

        return $current->current_period_ends_at->addMonth();
    }

    /** @param array<string, mixed> $payload */
    private function resolveStatus(array $payload): string
    {
        $transaction = (string) ($payload['transaction_status'] ?? '');
        $fraud = (string) ($payload['fraud_status'] ?? 'accept');

        return match ($transaction) {
            'settlement' => SubscriptionPayment::STATUS_PAID,
            'capture' => match ($fraud) {
                'accept' => SubscriptionPayment::STATUS_PAID,
                'challenge' => SubscriptionPayment::STATUS_CHALLENGE,
                default => SubscriptionPayment::STATUS_FAILED,
            },
            'pending' => SubscriptionPayment::STATUS_PENDING,
            'expire' => SubscriptionPayment::STATUS_EXPIRED,
            'refund', 'partial_refund' => SubscriptionPayment::STATUS_REFUNDED,
            default => SubscriptionPayment::STATUS_FAILED,
        };
    }

    private function reusablePayment(User $user, int $planId): ?SubscriptionPayment
    {
        return SubscriptionPayment::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->where('subscription_plan_id', $planId)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->whereNotNull('snap_token')
            ->where('expires_at', '>', now()->addMinutes(2))
            ->latest('id')
            ->first();
    }

    private function newOrderId(): string
    {
        return 'SUB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
