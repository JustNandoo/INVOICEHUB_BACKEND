<?php

namespace Database\Seeders;

use App\Enums\Ai\AiFeature;
use App\Enums\Ai\AiRunStatus;
use App\Enums\Ai\InsightAction;
use App\Enums\Ai\InsightSeverity;
use App\Enums\Ai\InsightType;
use App\Enums\Anomaly\AnomalyAction;
use App\Enums\Anomaly\AnomalyStatus;
use App\Enums\Anomaly\AnomalyType;
use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\AiUsageRecord;
use App\Models\BankTransaction;
use App\Models\FinancialAnomaly;
use App\Models\Invoice;
use App\Models\MonthlyRevenueTarget;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Notification\RevenueTargetService;
use App\Services\Report\WeeklyFinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoEngagementSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', DemoAccountSeeder::EMAIL)->firstOrFail();
        $run = $this->createAiInsights($user);
        $this->createAnomalies($user, $run);
        $this->createReportsTargetsAndNotifications($user);
    }

    private function createAiInsights(User $user): AiRun
    {
        $structuredOutput = ['insights' => [
            ['type' => 'receivable', 'severity' => 'warning', 'title' => 'Tagihan perlu perhatian', 'recommendedAction' => 'send_reminders'],
            ['type' => 'reconciliation', 'severity' => 'warning', 'title' => 'Mutasi perlu ditinjau', 'recommendedAction' => 'review_reconciliation'],
            ['type' => 'tax', 'severity' => 'info', 'title' => 'Laporan pajak periode berjalan', 'recommendedAction' => 'review_tax'],
        ]];
        $run = AiRun::query()->create([
            'user_id' => $user->id,
            'feature' => AiFeature::FinancialInsight->value,
            'provider' => 'demo',
            'model' => 'gemini-2.5-flash',
            'prompt_version' => 'v1',
            'status' => AiRunStatus::Succeeded->value,
            'input_hash' => hash('sha256', 'invoicehub-demo-financial-insight'),
            'structured_output' => $structuredOutput,
            'input_tokens' => 1_240,
            'output_tokens' => 386,
            'thinking_tokens' => 210,
            'estimated_cost' => 18_500,
            'latency_ms' => 1_840,
        ]);
        $run->forceFill(['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)])->saveQuietly();

        AiUsageRecord::query()->create([
            'user_id' => $user->id,
            'ai_run_id' => $run->id,
            'feature' => AiFeature::FinancialInsight->value,
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 1_240,
            'output_tokens' => 386,
            'estimated_cost' => 18_500,
            'credits_used' => 3,
        ]);

        $insights = [
            [
                'type' => InsightType::Receivable->value,
                'severity' => InsightSeverity::Warning->value,
                'title' => '4 invoice perlu perhatian',
                'summary' => 'Total piutang Rp8.750.000 masih terbuka. Dua invoice sudah melewati jatuh tempo dan sebaiknya segera diingatkan.',
                'evidence' => [['label' => 'Total piutang', 'value' => 8_750_000], ['label' => 'Invoice terbuka', 'value' => 4]],
                'recommended_action' => InsightAction::SendReminders->value,
                'action_url' => InsightAction::SendReminders->url(),
            ],
            [
                'type' => InsightType::Reconciliation->value,
                'severity' => InsightSeverity::Warning->value,
                'title' => '3 mutasi perlu ditinjau',
                'summary' => 'Satu transaksi memiliki kandidat invoice dengan kecocokan tinggi dan dua transaksi belum menemukan pasangan.',
                'evidence' => [['label' => 'Perlu konfirmasi', 'value' => 1], ['label' => 'Belum cocok', 'value' => 2]],
                'recommended_action' => InsightAction::ReviewReconciliation->value,
                'action_url' => InsightAction::ReviewReconciliation->url(),
            ],
            [
                'type' => InsightType::Tax->value,
                'severity' => InsightSeverity::Info->value,
                'title' => 'Laporan pajak Agustus siap ditinjau',
                'summary' => 'Pendapatan tercatat bulan ini sudah diperbarui. Selesaikan temuan transaksi COD sebelum laporan difinalisasi.',
                'evidence' => [['label' => 'Pendapatan tercatat', 'value' => 48_750_000], ['label' => 'Pendapatan tertunda', 'value' => 1_240_000]],
                'recommended_action' => InsightAction::ReviewTax->value,
                'action_url' => InsightAction::ReviewTax->url(),
            ],
        ];

        foreach ($insights as $index => $insight) {
            AiInsight::query()->create([
                'user_id' => $user->id,
                'ai_run_id' => $run->id,
                ...$insight,
                'position' => $index + 1,
                'valid_until' => now()->addDays(7),
            ]);
        }

        return $run;
    }

    private function createAnomalies(User $user, AiRun $run): void
    {
        $unmatched = BankTransaction::query()->where('user_id', $user->id)
            ->where('external_transaction_id', 'DEMO-TXN-UNMATCHED-001')->firstOrFail();
        $fee = BankTransaction::query()->where('user_id', $user->id)
            ->where('external_transaction_id', 'DEMO-TXN-FEE-001')->firstOrFail();
        $overdue = Invoice::query()->where('user_id', $user->id)
            ->where('status', Invoice::STATUS_UNPAID)->whereDate('due_date', '<', today())->firstOrFail();

        $fixtures = [
            [
                'type' => AnomalyType::UnmatchedIncoming->value,
                'severity' => InsightSeverity::Warning->value,
                'title' => 'Pendapatan COD belum tercatat',
                'description' => 'Settlement COD Rp1.240.000 belum terhubung ke invoice maupun buku pajak.',
                'amount_at_risk' => 120_000,
                'source_type' => 'bank_transaction', 'source_id' => $unmatched->id,
                'metadata' => ['transactionAmount' => 1_240_000, 'reference' => $unmatched->reference],
                'recommended_action' => AnomalyAction::ReviewTransaction->value,
                'action_url' => '/reconciliation',
            ],
            [
                'type' => AnomalyType::ExcessiveFee->value,
                'severity' => InsightSeverity::Warning->value,
                'title' => 'Potongan marketplace lebih tinggi dari pola normal',
                'description' => 'Biaya layanan Rp78.000 perlu dibandingkan dengan rincian settlement marketplace.',
                'amount_at_risk' => 78_000,
                'source_type' => 'bank_transaction', 'source_id' => $fee->id,
                'metadata' => ['feeAmount' => 78_000, 'expectedMaximum' => 50_000],
                'recommended_action' => AnomalyAction::VerifyFee->value,
                'action_url' => '/reconciliation',
            ],
            [
                'type' => AnomalyType::UnsettledBalance->value,
                'severity' => InsightSeverity::Critical->value,
                'title' => 'Invoice jatuh tempo belum ditindaklanjuti',
                'description' => 'Invoice lama masih memiliki saldo terbuka dan belum memiliki aktivitas pengingat terbaru.',
                'amount_at_risk' => 142_000,
                'source_type' => 'invoice', 'source_id' => $overdue->id,
                'metadata' => ['invoiceNumber' => $overdue->number, 'balanceDue' => $overdue->balance_due],
                'recommended_action' => AnomalyAction::ContactCustomer->value,
                'action_url' => '/invoices/'.$overdue->id,
            ],
        ];

        foreach ($fixtures as $index => $fixture) {
            FinancialAnomaly::query()->create([
                'user_id' => $user->id,
                ...$fixture,
                'status' => AnomalyStatus::Open->value,
                'ai_run_id' => $index === 0 ? $run->id : null,
                'explanation' => $index === 0 ? 'Dana masuk berasal dari settlement COD, tetapi pesanan terkait belum masuk ke invoice sehingga transaksi belum dapat dicocokkan otomatis.' : null,
                'likely_cause' => $index === 0 ? 'Sinkronisasi pesanan COD marketplace belum selesai.' : null,
                'prevention_tip' => $index === 0 ? 'Sinkronkan pesanan marketplace sebelum jadwal pencairan dana.' : null,
                'explained_at' => $index === 0 ? now()->subMinutes(12) : null,
                'detected_at' => now()->subHours($index + 1),
            ]);
        }
    }

    private function createReportsTargetsAndNotifications(User $user): void
    {
        $lastWeekStart = CarbonImmutable::now()->subWeek()->startOfWeek();
        $weeklyReport = app(WeeklyFinancialReportService::class)->generate(
            $user,
            $lastWeekStart,
            $lastWeekStart->endOfWeek(),
        );

        $period = CarbonImmutable::now();
        MonthlyRevenueTarget::query()->create([
            'user_id' => $user->id,
            'year' => $period->year,
            'month' => $period->month,
            'amount' => 45_000_000,
        ]);
        app(RevenueTargetService::class)->evaluate($user, $period->year, $period->month);

        $latestPaid = Invoice::query()->where('user_id', $user->id)
            ->where('status', Invoice::STATUS_PAID)->latest('paid_at')->firstOrFail();
        $dueSoon = Invoice::query()->where('user_id', $user->id)
            ->where('status', Invoice::STATUS_UNPAID)->whereDate('due_date', '>=', today())
            ->orderBy('due_date')->firstOrFail();
        $latestCustomer = $user->customers()->latest('created_at')->firstOrFail();
        $notifications = app(NotificationService::class);

        $items = [
            $notifications->createOnce(
                $user, BusinessNotificationType::InvoicePaid, 'demo:invoice-paid',
                "Invoice {$latestPaid->number} lunas",
                "{$latestPaid->customer_name} melakukan pembayaran sebesar Rp ".number_format($latestPaid->total_amount, 0, ',', '.').'.',
                NotificationTone::Blue, NotificationIcon::Receipt, '/invoices/'.$latestPaid->id,
                ['invoiceId' => $latestPaid->id, 'amount' => $latestPaid->total_amount],
            ),
            $notifications->createOnce(
                $user, BusinessNotificationType::InvoiceDueSoon, 'demo:invoice-due-soon',
                'Jatuh tempo mendekat',
                "Invoice {$dueSoon->number} untuk {$dueSoon->customer_name} jatuh tempo dalam beberapa hari.",
                NotificationTone::Yellow, NotificationIcon::Alert, '/invoices/'.$dueSoon->id,
                ['invoiceId' => $dueSoon->id, 'dueDate' => $dueSoon->due_date->toDateString()],
            ),
            $notifications->createOnce(
                $user, BusinessNotificationType::RevenueTargetReached, 'demo:target-reached',
                'Target pendapatan tercapai!',
                'Pendapatan bulan ini telah melewati target Rp45.000.000.',
                NotificationTone::Pink, NotificationIcon::Celebration, '/dashboard',
                ['targetAmount' => 45_000_000, 'currentRevenue' => 48_750_000],
            ),
            $notifications->createOnce(
                $user, BusinessNotificationType::CustomerCreated, 'demo:customer-created',
                'Pelanggan baru',
                "{$latestCustomer->name} berhasil ditambahkan ke daftar pelanggan.",
                NotificationTone::Muted, NotificationIcon::Customer, '/customers/'.$latestCustomer->id,
                ['customerId' => $latestCustomer->id],
            ),
            $notifications->createOnce(
                $user, BusinessNotificationType::WeeklyReportReady, 'demo:weekly-report',
                'Laporan mingguan siap',
                'Ringkasan arus kas minggu lalu sudah dapat diunduh.',
                NotificationTone::Muted, NotificationIcon::File, '/weekly-reports/'.$weeklyReport->id.'/pdf',
                ['weeklyReportId' => $weeklyReport->id],
            ),
        ];

        foreach ($items as $index => $notification) {
            $notification->forceFill([
                'created_at' => now()->subHours($index === 0 ? 0 : ($index * 5)),
                'updated_at' => now()->subHours($index === 0 ? 0 : ($index * 5)),
                'read_at' => $index >= 3 ? now()->subHours($index) : null,
            ])->saveQuietly();
        }
    }
}
