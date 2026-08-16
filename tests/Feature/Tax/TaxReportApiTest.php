<?php

namespace Tests\Feature\Tax;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\TaxpayerProfile;
use App\Models\TaxPeriodReport;
use App\Models\User;
use App\Services\Tax\TaxpayerProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_report_api_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/tax-profile')->assertUnauthorized();
        $user = User::factory()->unverified()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/tax-profile')->assertForbidden();
    }

    public function test_user_can_save_tax_profile_and_npwp_is_encrypted_and_masked(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))->putJson('/api/v1/tax-profile', [
            'taxpayerType' => 'entity',
            'taxpayerName' => 'PT Sahabat UMKM',
            'businessName' => 'InvoiceHub Store',
            'npwp' => '1234567890123456',
        ])->assertOk()
            ->assertJsonPath('data.taxProfile.npwpMasked', '•••• •••• •••• 3456')
            ->assertJsonPath('data.taxProfile.taxRule.taxRate', 0.5);

        $profile = TaxpayerProfile::query()->firstOrFail();
        $this->assertSame('1234567890123456', $profile->npwp);
        $this->assertStringNotContainsString('1234567890123456', (string) $profile->getRawOriginal('npwp'));
        $this->assertArrayNotHasKey('npwp', $profile->toArray());
    }

    public function test_recalculate_uses_active_payments_and_returns_clear_english_keys(): void
    {
        $user = User::factory()->create();
        $this->profile($user, 'entity');
        $date = CarbonImmutable::create(2026, 10, 15, 10);
        $this->payment($user, 48_750_000, $date);
        $voided = $this->payment($user, 1_000_000, $date);
        $voided->update(['voided_at' => $date->addDay(), 'void_reason' => 'Test reversal']);

        $response = $this->withToken($this->token($user))
            ->postJson('/api/v1/tax-reports/2026/10/recalculate');

        $response->assertOk()
            ->assertJsonPath('data.report.period.monthName', 'Oktober')
            ->assertJsonPath('data.report.monthlySummary.totalRevenue', 48_750_000)
            ->assertJsonPath('data.report.monthlySummary.taxableRevenue', 48_750_000)
            ->assertJsonPath('data.report.monthlySummary.taxRate', 0.5)
            ->assertJsonPath('data.report.monthlySummary.estimatedTax', 243_750)
            ->assertJsonPath('data.report.monthlySummary.paidInvoiceCount', 1)
            ->assertJsonPath('data.report.reportStatus', 'draft')
            ->assertJsonPath('data.report.isLocked', false);

        $this->assertDatabaseHas('tax_ledger_entries', ['source_id' => $voided->id, 'status' => 'excluded']);
        $this->assertDatabaseCount('tax_report_sources', 1);
    }

    public function test_individual_threshold_is_applied_across_the_year(): void
    {
        $user = User::factory()->create();
        $this->profile($user, 'individual');
        $this->payment($user, 490_000_000, CarbonImmutable::create(2026, 9, 1));
        $this->payment($user, 20_000_000, CarbonImmutable::create(2026, 10, 1));
        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/9/recalculate')
            ->assertOk()->assertJsonPath('data.report.monthlySummary.estimatedTax', 0);
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/recalculate')
            ->assertOk()->assertJsonPath('data.report.monthlySummary.taxableRevenue', 10_000_000)
            ->assertJsonPath('data.report.monthlySummary.estimatedTax', 50_000);
    }

    public function test_unmatched_bank_transaction_creates_finding_and_can_be_included(): void
    {
        $user = User::factory()->create();
        $this->profile($user, 'entity');
        $account = BankAccount::factory()->for($user, 'owner')->create();
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 1_240_000, 'transaction_at' => CarbonImmutable::create(2026, 10, 12), 'status' => 'unmatched',
        ]);
        $token = $this->token($user);

        $recalculated = $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/recalculate')
            ->assertOk()->assertJsonPath('data.report.totalFindings', 1);
        $reportId = $recalculated->json('data.report.id');

        $list = $this->withToken($token)->getJson('/api/v1/tax-audit-findings?year=2026&month=10')
            ->assertOk()->assertJsonPath('data.totalFindings', 1)
            ->assertJsonPath('data.findings.0.type', 'unmatched_bank_transaction')
            ->assertJsonPath('data.findings.0.amount', 1_240_000);

        $findingId = $list->json('data.findings.0.id');
        $this->withToken($token)->postJson("/api/v1/tax-audit-findings/{$findingId}/include")
            ->assertOk()->assertJsonPath('data.reportNeedsRecalculation', true)
            ->assertJsonPath('data.finding.status', 'resolved');

        $this->assertDatabaseHas('tax_ledger_entries', [
            'user_id' => $user->id, 'source_type' => 'bank_transaction',
            'source_id' => $transaction->id, 'amount' => 1_240_000,
        ]);
        $this->assertDatabaseHas('tax_period_reports', ['id' => $reportId, 'findings_count' => 0]);
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/finalize')
            ->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/recalculate')
            ->assertOk()->assertJsonPath('data.report.monthlySummary.totalRevenue', 1_240_000);
    }

    public function test_finalize_requires_resolved_findings_then_locks_and_marks_reported(): void
    {
        $user = User::factory()->create();
        $this->profile($user, 'entity');
        $account = BankAccount::factory()->for($user, 'owner')->create();
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'transaction_at' => CarbonImmutable::create(2026, 10, 1), 'status' => 'unmatched',
        ]);
        $token = $this->token($user);
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/recalculate')->assertOk();

        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/finalize')
            ->assertUnprocessable()->assertJsonValidationErrors('report');
        $findingId = $this->withToken($token)->getJson('/api/v1/tax-audit-findings?year=2026&month=10')->json('data.findings.0.id');
        $this->withToken($token)->postJson("/api/v1/tax-audit-findings/{$findingId}/resolve", [
            'resolution' => 'not_taxable', 'notes' => 'Owner capital deposit',
        ])->assertOk()->assertJsonPath('data.finding.status', 'dismissed');

        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/finalize')
            ->assertOk()->assertJsonPath('data.report.reportStatus', 'ready')
            ->assertJsonPath('data.report.isLocked', true);
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/recalculate')
            ->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/mark-reported', [
            'referenceNumber' => 'DJP-2026-10-001',
        ])->assertOk()->assertJsonPath('data.report.reportStatus', 'reported')
            ->assertJsonPath('data.report.referenceNumber', 'DJP-2026-10-001');
    }

    public function test_monthly_and_annual_pdf_are_real_pdf_files(): void
    {
        $user = User::factory()->create();
        $this->profile($user, 'entity');
        $this->payment($user, 2_000_000, CarbonImmutable::create(2026, 10, 1));
        $token = $this->token($user);
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/recalculate')->assertOk();
        $this->withToken($token)->postJson('/api/v1/tax-reports/2026/10/finalize')->assertOk();

        foreach (['/api/v1/tax-reports/2026/10/pdf', '/api/v1/tax-reports/2026/annual-pdf'] as $url) {
            $response = $this->withToken($token)->get($url);
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
    }

    public function test_tax_data_is_isolated_per_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->profile($owner, 'entity');
        $this->profile($intruder, 'entity');
        $this->payment($owner, 5_000_000, CarbonImmutable::create(2026, 10, 1));
        $this->withToken($this->token($owner))->postJson('/api/v1/tax-reports/2026/10/recalculate')->assertOk();

        $this->actingAs($intruder, 'sanctum')->getJson('/api/v1/tax-reports/2026/10')
            ->assertOk()->assertJsonPath('data.report.monthlySummary.totalRevenue', 0);
        $this->assertSame(0, TaxPeriodReport::query()->where('user_id', $intruder->id)->count());
    }

    private function profile(User $user, string $type): void
    {
        $this->app->make(TaxpayerProfileService::class)->update($user, [
            'taxpayerType' => $type, 'taxpayerName' => $user->name, 'businessName' => $user->business_name,
        ]);
    }

    private function payment(User $user, int $amount, CarbonImmutable $date): InvoicePayment
    {
        $invoice = Invoice::factory()->for($user, 'owner')->paid()->create([
            'total_amount' => $amount, 'paid_amount' => $amount, 'balance_due' => 0, 'paid_at' => $date,
        ]);

        return $invoice->payments()->create([
            'recorded_by' => $user->id, 'amount' => $amount, 'method' => 'bank_transfer',
            'source' => 'manual', 'paid_at' => $date,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('tax-test')->plainTextToken;
    }
}
