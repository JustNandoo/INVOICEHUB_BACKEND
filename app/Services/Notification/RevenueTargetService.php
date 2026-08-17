<?php

namespace App\Services\Notification;

use App\Models\InvoicePayment;
use App\Models\MonthlyRevenueTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RevenueTargetService
{
    /** @return array{target: MonthlyRevenueTarget|null, currentRevenue: int, progressPercent: float, isReached: bool} */
    public function summary(User $user, int $year, int $month): array
    {
        $target = MonthlyRevenueTarget::query()->where([
            'user_id' => $user->id, 'year' => $year, 'month' => $month,
        ])->first();
        $currentRevenue = $this->revenue($user, $year, $month);

        return [
            'target' => $target,
            'currentRevenue' => $currentRevenue,
            'progressPercent' => $target ? round(min(100, ($currentRevenue / $target->amount) * 100), 2) : 0,
            'isReached' => $target !== null && $currentRevenue >= $target->amount,
        ];
    }

    public function set(User $user, int $year, int $month, int $amount): MonthlyRevenueTarget
    {
        $target = MonthlyRevenueTarget::query()->updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            ['amount' => $amount],
        );
        if ($target->reached_at && $this->revenue($user, $year, $month) < $amount) {
            $target->update(['reached_at' => null]);
        }

        return $target->refresh();
    }

    /** @return array{target: MonthlyRevenueTarget|null, currentRevenue: int, newlyReached: bool} */
    public function evaluate(User $user, int $year, int $month): array
    {
        return DB::transaction(function () use ($user, $year, $month): array {
            $target = MonthlyRevenueTarget::query()->where([
                'user_id' => $user->id, 'year' => $year, 'month' => $month,
            ])->lockForUpdate()->first();
            $currentRevenue = $this->revenue($user, $year, $month);
            $newlyReached = $target !== null && $target->reached_at === null && $currentRevenue >= $target->amount;
            if ($newlyReached) {
                $target->update(['reached_at' => now()]);
            }

            return ['target' => $target?->refresh(), 'currentRevenue' => $currentRevenue, 'newlyReached' => $newlyReached];
        }, 3);
    }

    private function revenue(User $user, int $year, int $month): int
    {
        $start = CarbonImmutable::create($year, $month)->startOfMonth();

        return (int) InvoicePayment::query()->active()
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('paid_at', [$start, $start->endOfMonth()])
            ->sum('amount');
    }
}
