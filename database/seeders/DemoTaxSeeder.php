<?php

namespace Database\Seeders;

use App\Models\MarketplaceOrder;
use App\Models\TaxLedgerEntry;
use App\Models\User;
use App\Services\Tax\TaxLedgerService;
use App\Services\Tax\TaxpayerProfileService;
use App\Services\Tax\TaxReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoTaxSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', DemoAccountSeeder::email())->firstOrFail();
        app(TaxpayerProfileService::class)->update($user, [
            'taxpayerType' => 'entity',
            'taxpayerName' => 'Rani Prameswari',
            'businessName' => 'CV Kopi Karsa Nusantara',
            'npwp' => '1234567890123456',
            'taxScheme' => 'final_umkm',
            'accountingMethod' => 'cash_basis',
            'effectiveFrom' => CarbonImmutable::now()->subYear()->startOfYear()->toDateString(),
        ]);

        $years = $user->invoices()->whereNotNull('paid_at')->get()->pluck('paid_at')
            ->filter()->map(fn ($date): int => $date->year)->unique();
        foreach ($years as $year) {
            app(TaxLedgerService::class)->syncInvoicePayments($user, $year);
        }

        $codOrder = MarketplaceOrder::query()->where('user_id', $user->id)->firstOrFail();
        TaxLedgerEntry::query()->create([
            'user_id' => $user->id,
            'source_type' => 'marketplace_order',
            'source_id' => $codOrder->id,
            'entry_type' => 'revenue',
            'amount' => 1_240_000,
            'status' => TaxLedgerEntry::STATUS_PENDING,
            'is_taxable' => true,
            'recognized_at' => now()->subDay(),
            'description' => 'Settlement COD menunggu verifikasi dan pencocokan invoice.',
            'metadata' => ['platform' => 'tokopedia', 'demo' => true],
        ]);

        $reports = app(TaxReportService::class);
        for ($offset = 5; $offset >= 0; $offset--) {
            $period = CarbonImmutable::now()->subMonthsNoOverflow($offset);
            $report = $reports->recalculate($user, $period->year, $period->month);

            if ($offset >= 2) {
                $reports->finalize($user, $period->year, $period->month);
                $reports->markReported($user, $period->year, $period->month, [
                    'reportedAt' => $period->endOfMonth()->addDays(8)->setTime(10, 0),
                    'referenceNumber' => sprintf('BPE-DEMO-%d-%02d', $period->year, $period->month),
                    'notes' => 'Laporan demo telah diverifikasi dan ditandai selesai.',
                ]);
            } elseif ($offset === 1) {
                $reports->finalize($user, $period->year, $period->month);
            } else {
                $report->update(['notes' => 'Periode berjalan. Tinjau temuan sebelum finalisasi.']);
            }
        }
    }
}
