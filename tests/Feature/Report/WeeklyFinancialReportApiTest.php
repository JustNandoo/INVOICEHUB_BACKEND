<?php

namespace Tests\Feature\Report;

use App\Enums\Notification\BusinessNotificationType;
use App\Jobs\Report\GenerateUserWeeklyFinancialReport;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyFinancialReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_job_generates_metrics_notification_and_downloadable_pdf(): void
    {
        $user = User::factory()->create(['business_name' => 'InvoiceHub Test Business']);
        Customer::factory()->for($user, 'owner')->create(['created_at' => '2026-08-12 10:00:00']);
        $paidInvoice = Invoice::factory()->for($user, 'owner')->paid()->create([
            'issue_date' => '2026-08-11', 'due_date' => '2026-08-15',
            'total_amount' => 1_500_000, 'paid_amount' => 1_500_000, 'balance_due' => 0,
        ]);
        $paidInvoice->payments()->create([
            'recorded_by' => $user->id, 'amount' => 1_500_000,
            'method' => 'bank_transfer', 'paid_at' => '2026-08-13 09:00:00',
        ]);
        Invoice::factory()->for($user, 'owner')->create([
            'issue_date' => '2026-08-14', 'due_date' => '2026-08-15',
            'total_amount' => 600_000, 'balance_due' => 600_000,
        ]);

        $job = new GenerateUserWeeklyFinancialReport($user->id, '2026-08-10', '2026-08-16');
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('weekly_financial_reports', ['user_id' => $user->id]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id, 'type' => BusinessNotificationType::WeeklyReportReady->value,
        ]);
        $report = $user->weeklyFinancialReports()->firstOrFail();
        $this->assertSame('2026-08-10', $report->period_start->toDateString());
        $this->assertSame('2026-08-16', $report->period_end->toDateString());
        $this->assertSame(1_500_000, $report->metrics['receivedAmount']);
        $this->assertSame(1, $report->metrics['newCustomerCount']);
        $token = $user->createToken('weekly-report-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/weekly-reports')
            ->assertOk()->assertJsonPath('data.reports.0.id', $report->id)
            ->assertJsonPath('data.reports.0.metrics.receivedAmount', 1_500_000);
        $this->withToken($token)->get("/api/v1/weekly-reports/{$report->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_user_cannot_access_weekly_report(): void
    {
        $owner = User::factory()->create();
        $job = new GenerateUserWeeklyFinancialReport($owner->id, '2026-08-10', '2026-08-16');
        app()->call([$job, 'handle']);
        $report = $owner->weeklyFinancialReports()->firstOrFail();
        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('intruder')->plainTextToken)
            ->getJson("/api/v1/weekly-reports/{$report->id}")->assertNotFound();
    }
}
