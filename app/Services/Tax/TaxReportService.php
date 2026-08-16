<?php

namespace App\Services\Tax;

use App\Models\InvoicePayment;
use App\Models\TaxAuditFinding;
use App\Models\TaxLedgerEntry;
use App\Models\TaxpayerProfile;
use App\Models\TaxPeriodReport;
use App\Models\TaxReportSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxReportService
{
    public function __construct(
        private readonly TaxLedgerService $ledger,
        private readonly TaxAuditService $audit,
    ) {}

    public function profile(User $user): TaxpayerProfile
    {
        $profile = TaxpayerProfile::query()->with('taxRule')->where('user_id', $user->id)->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'taxProfile' => ['Complete your tax profile before calculating a tax report.'],
            ]);
        }

        return $profile;
    }

    public function preview(User $user, int $year, int $month): TaxPeriodReport
    {
        $profile = $this->profile($user);
        $existing = TaxPeriodReport::query()->with('taxRule')->where([
            'user_id' => $user->id, 'year' => $year, 'month' => $month,
        ])->first();

        if ($existing?->isLocked()) {
            return $existing;
        }

        $this->ledger->syncInvoicePayments($user, $year);
        $numbers = $this->calculate($user, $profile, $year, $month);

        return new TaxPeriodReport([
            ...$numbers,
            'user_id' => $user->id,
            'tax_rule_version_id' => $profile->tax_rule_version_id,
            'year' => $year,
            'month' => $month,
            'status' => TaxPeriodReport::STATUS_DRAFT,
            'findings_count' => TaxAuditFinding::query()->where([
                'user_id' => $user->id, 'year' => $year, 'month' => $month, 'status' => TaxAuditFinding::STATUS_OPEN,
            ])->count(),
            'calculated_at' => now(),
        ]);
    }

    public function recalculate(User $user, int $year, int $month): TaxPeriodReport
    {
        $profile = $this->profile($user);

        return DB::transaction(function () use ($user, $profile, $year, $month): TaxPeriodReport {
            $existing = TaxPeriodReport::query()->where([
                'user_id' => $user->id, 'year' => $year, 'month' => $month,
            ])->lockForUpdate()->first();

            if ($existing?->isLocked()) {
                throw ValidationException::withMessages(['report' => ['A finalized report cannot be recalculated.']]);
            }

            $this->ledger->syncInvoicePayments($user, $year);
            $numbers = $this->calculate($user, $profile, $year, $month);
            $report = TaxPeriodReport::query()->updateOrCreate(
                ['user_id' => $user->id, 'year' => $year, 'month' => $month],
                [
                    ...$numbers,
                    'tax_rule_version_id' => $profile->tax_rule_version_id,
                    'status' => TaxPeriodReport::STATUS_DRAFT,
                    'calculated_at' => now(),
                    'locked_hash' => null,
                ],
            );

            $this->snapshotSources($report, $user, $year, $month);
            $report->update(['findings_count' => $this->audit->refresh($user, $report)]);

            return $report->fresh('taxRule');
        });
    }

    /** @return Collection<int, TaxPeriodReport> */
    public function monthly(User $user, int $year, int $months): Collection
    {
        $this->profile($user);
        $lastMonth = $year === now()->year ? now()->month : 12;
        $firstMonth = max(1, $lastMonth - $months + 1);

        return collect(range($firstMonth, $lastMonth))
            ->map(fn (int $month): TaxPeriodReport => $this->preview($user, $year, $month));
    }

    public function finalize(User $user, int $year, int $month): TaxPeriodReport
    {
        return DB::transaction(function () use ($user, $year, $month): TaxPeriodReport {
            $report = $this->ownedReport($user, $year, $month, true);
            if ($report->isLocked()) {
                return $report;
            }

            if ($report->status === TaxPeriodReport::STATUS_REVISION_REQUIRED) {
                throw ValidationException::withMessages([
                    'report' => ['Recalculate this report after changing its tax ledger.'],
                ]);
            }

            $openFindings = TaxAuditFinding::query()->where('tax_period_report_id', $report->id)
                ->where('status', TaxAuditFinding::STATUS_OPEN)->count();
            if ($openFindings > 0) {
                throw ValidationException::withMessages([
                    'report' => ["Resolve {$openFindings} open tax finding(s) before finalizing this report."],
                ]);
            }

            $report->update([
                'status' => TaxPeriodReport::STATUS_READY,
                'findings_count' => 0,
                'finalized_at' => now(),
                'locked_hash' => $this->reportHash($report),
            ]);

            return $report->fresh('taxRule');
        });
    }

    /** @param array<string, mixed> $data */
    public function markReported(User $user, int $year, int $month, array $data): TaxPeriodReport
    {
        $report = $this->ownedReport($user, $year, $month, true);
        if (! $report->isLocked()) {
            throw ValidationException::withMessages(['report' => ['Finalize this report before marking it as reported.']]);
        }

        $report->update([
            'status' => TaxPeriodReport::STATUS_REPORTED,
            'reported_at' => $data['reportedAt'] ?? now(),
            'reference_number' => trim($data['referenceNumber']),
            'notes' => $data['notes'] ?? $report->notes,
        ]);

        return $report->fresh('taxRule');
    }

    public function ownedReport(User $user, int $year, int $month, bool $lock = false): TaxPeriodReport
    {
        $query = TaxPeriodReport::query()->with('taxRule')->where([
            'user_id' => $user->id, 'year' => $year, 'month' => $month,
        ]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    /** @return array<string, int|array<string, mixed>> */
    private function calculate(User $user, TaxpayerProfile $profile, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month)->startOfMonth();
        $end = $start->endOfMonth();
        $recorded = TaxLedgerEntry::query()->where('user_id', $user->id)
            ->where('status', TaxLedgerEntry::STATUS_RECORDED)->whereBetween('recognized_at', [$start, $end]);
        $totalRevenue = (int) (clone $recorded)->sum('amount');
        $currentTaxableRevenue = (int) (clone $recorded)->where('is_taxable', true)->sum('amount');
        $pendingRevenue = (int) TaxLedgerEntry::query()->where('user_id', $user->id)
            ->where('status', TaxLedgerEntry::STATUS_PENDING)->whereBetween('recognized_at', [$start, $end])->sum('amount');

        $yearStart = $start->startOfYear();
        $ytdTaxable = (int) TaxLedgerEntry::query()->where('user_id', $user->id)
            ->where('status', TaxLedgerEntry::STATUS_RECORDED)->where('is_taxable', true)
            ->whereBetween('recognized_at', [$yearStart, $end])->sum('amount');
        $priorTaxable = $ytdTaxable - $currentTaxableRevenue;
        $threshold = $profile->taxpayer_type === 'individual'
            ? $profile->taxRule->individual_non_taxable_threshold
            : 0;
        $taxableRevenue = max(0, $ytdTaxable - $threshold) - max(0, $priorTaxable - $threshold);
        $rate = $profile->taxRule->rate_basis_points;

        $paidInvoiceCount = InvoicePayment::query()->active()
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('paid_at', [$start, $end])->distinct('invoice_id')->count('invoice_id');

        return [
            'total_revenue' => $totalRevenue,
            'taxable_revenue' => $taxableRevenue,
            'pending_revenue' => $pendingRevenue,
            'paid_invoice_count' => $paidInvoiceCount,
            'tax_rate_basis_points' => $rate,
            'estimated_tax' => intdiv(($taxableRevenue * $rate) + 5000, 10000),
            'calculation_metadata' => [
                'accountingMethod' => $profile->accounting_method,
                'yearToDateTaxableRevenue' => $ytdTaxable,
                'appliedNonTaxableThreshold' => $threshold,
                'calculationVersion' => 1,
            ],
        ];
    }

    private function snapshotSources(TaxPeriodReport $report, User $user, int $year, int $month): void
    {
        $start = CarbonImmutable::create($year, $month)->startOfMonth();
        $entries = TaxLedgerEntry::query()->where('user_id', $user->id)
            ->whereIn('status', [TaxLedgerEntry::STATUS_RECORDED, TaxLedgerEntry::STATUS_PENDING])
            ->whereBetween('recognized_at', [$start, $start->endOfMonth()])->get();

        $report->sources()->delete();
        $entries->each(fn (TaxLedgerEntry $entry) => TaxReportSource::query()->create([
            'tax_period_report_id' => $report->id, 'tax_ledger_entry_id' => $entry->id,
            'source_type' => $entry->source_type, 'source_id' => $entry->source_id,
            'amount' => $entry->amount, 'recognized_at' => $entry->recognized_at,
            'metadata' => $entry->metadata,
        ]));
    }

    private function reportHash(TaxPeriodReport $report): string
    {
        $sources = $report->sources()->orderBy('id')->get(['source_type', 'source_id', 'amount', 'recognized_at'])->toJson();

        return hash('sha256', implode('|', [
            $report->user_id, $report->year, $report->month, $report->total_revenue,
            $report->taxable_revenue, $report->estimated_tax, $report->tax_rule_version_id, $sources,
        ]));
    }
}
