<?php

use App\Http\Controllers\Api\Ai\AiAnomalyExplanationController;
use App\Http\Controllers\Api\Ai\AiFeedbackController;
use App\Http\Controllers\Api\Ai\AiInsightController;
use App\Http\Controllers\Api\Ai\AiReconciliationController;
use App\Http\Controllers\Api\Ai\AiReminderDraftController;
use App\Http\Controllers\Api\Ai\AiUsageController;
use App\Http\Controllers\Api\Anomaly\AnomalyController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Blog\BlogController;
use App\Http\Controllers\Api\Blog\BlogManagementController;
use App\Http\Controllers\Api\Customer\CustomerController;
use App\Http\Controllers\Api\Customer\CustomerSearchController;
use App\Http\Controllers\Api\Invoice\InvoiceController;
use App\Http\Controllers\Api\Marketplace\MarketplaceCallbackController;
use App\Http\Controllers\Api\Marketplace\MarketplaceConnectionController;
use App\Http\Controllers\Api\Marketplace\MarketplaceOrderController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Notification\RevenueTargetController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Reconciliation\BankAccountController;
use App\Http\Controllers\Api\Reconciliation\BankTransactionController;
use App\Http\Controllers\Api\Reconciliation\BankTransactionImportController;
use App\Http\Controllers\Api\Reconciliation\ReconciliationController;
use App\Http\Controllers\Api\Report\WeeklyFinancialReportController;
use App\Http\Controllers\Api\Subscription\SubscriptionCheckoutController;
use App\Http\Controllers\Api\Subscription\SubscriptionController;
use App\Http\Controllers\Api\Tax\TaxAuditFindingController;
use App\Http\Controllers\Api\Tax\TaxpayerProfileController;
use App\Http\Controllers\Api\Tax\TaxReportController;
use App\Http\Controllers\Api\Webhook\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'verified', 'throttle:notification-management'])
    ->group(function (): void {
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->whereUuid('notification');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
            ->whereUuid('notification');

        Route::get('/revenue-targets/{year}/{month}', [RevenueTargetController::class, 'show'])
            ->whereNumber(['year', 'month']);
        Route::put('/revenue-targets/{year}/{month}', [RevenueTargetController::class, 'update'])
            ->whereNumber(['year', 'month']);

        Route::get('/weekly-reports', [WeeklyFinancialReportController::class, 'index']);
        Route::get('/weekly-reports/{weeklyReport}', [WeeklyFinancialReportController::class, 'show'])
            ->whereNumber('weeklyReport');
        Route::get('/weekly-reports/{weeklyReport}/pdf', [WeeklyFinancialReportController::class, 'pdf'])
            ->whereNumber('weeklyReport');
    });

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'verified', 'throttle:invoice-management'])
    ->group(function (): void {
        Route::get('/invoices/summary', [InvoiceController::class, 'summary']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice');
        Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])->whereNumber('invoice');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->whereNumber('invoice');
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->whereNumber('invoice');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->whereNumber('invoice');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->whereNumber('invoice');
    });

Route::get('/v1/subscriptions/plans', [SubscriptionController::class, 'plans'])
    ->middleware('throttle:60,1');

Route::prefix('v1/subscription')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'show']);
        Route::get('/usage', [SubscriptionController::class, 'usage']);
        Route::get('/payment-history', [SubscriptionController::class, 'paymentHistory']);
        Route::post('/checkout', [SubscriptionCheckoutController::class, 'store'])
            ->middleware('throttle:subscription-checkout');
    });

// Notifikasi Midtrans dipanggil server mereka, bukan browser pengguna, jadi tanpa
// Sanctum. Keabsahannya dijamin signature_key yang diverifikasi di service.
Route::post('/v1/webhooks/midtrans', MidtransWebhookController::class)
    ->middleware('throttle:120,1');

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'verified', 'throttle:customer-management'])
    ->group(function (): void {
        Route::get('/customers/summary', [CustomerController::class, 'summary']);
        Route::get('/customers/search', CustomerSearchController::class);
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer');
        Route::get('/customers/{customer}/invoices', [CustomerController::class, 'invoices'])->whereNumber('customer');
    });

Route::prefix('v1/profile')
    ->middleware(['auth:sanctum', 'verified', 'throttle:profile-management'])
    ->group(function (): void {
        Route::get('/', [ProfileController::class, 'show']);
        Route::patch('/', [ProfileController::class, 'update']);
        Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
        Route::put('/password', [ProfileController::class, 'changePassword'])->middleware('throttle:profile-password');
    });

Route::prefix('v1')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:reconciliation-management',
        'subscription.feature:reconciliation.automatic',
    ])
    ->group(function (): void {
        Route::get('/bank-accounts', [BankAccountController::class, 'index']);
        Route::post('/bank-accounts/{bankAccount}/sync', [BankAccountController::class, 'sync'])->whereNumber('bankAccount');

        Route::get('/bank-transactions', [BankTransactionController::class, 'index']);
        Route::post('/bank-transactions/imports', [BankTransactionImportController::class, 'store']);
        Route::get('/bank-transactions/imports/{import}', [BankTransactionImportController::class, 'show'])->whereNumber('import');
        Route::get('/bank-transactions/{bankTransaction}', [BankTransactionController::class, 'show'])->whereNumber('bankTransaction');
        Route::get('/bank-transactions/{bankTransaction}/candidates', [BankTransactionController::class, 'candidates'])->whereNumber('bankTransaction');
        Route::post('/bank-transactions/{bankTransaction}/ignore', [BankTransactionController::class, 'ignore'])->whereNumber('bankTransaction');
        Route::post('/bank-transactions/{bankTransaction}/reject-candidate', [BankTransactionController::class, 'rejectCandidate'])->whereNumber('bankTransaction');

        Route::get('/reconciliation/summary', [ReconciliationController::class, 'summary']);
        Route::get('/reconciliations', [ReconciliationController::class, 'index']);
        Route::post('/reconciliations', [ReconciliationController::class, 'store']);
        Route::post('/reconciliations/{reconciliation}/reverse', [ReconciliationController::class, 'reverse'])->whereNumber('reconciliation');
    });

Route::prefix('v1/ai')
    ->middleware(['auth:sanctum', 'verified', 'throttle:ai-management'])
    ->group(function (): void {
        Route::get('/usage', [AiUsageController::class, 'show']);
        Route::post('/runs/{aiRun}/feedback', [AiFeedbackController::class, 'store'])->whereNumber('aiRun');
    });

Route::prefix('v1')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:reconciliation-management',
        'subscription.feature:anomaly.detection',
    ])
    ->group(function (): void {
        Route::get('/anomalies/summary', [AnomalyController::class, 'summary']);
        Route::get('/anomalies', [AnomalyController::class, 'index']);
        Route::post('/anomalies/scan', [AnomalyController::class, 'scan']);
        Route::post('/anomalies/{anomaly}/resolve', [AnomalyController::class, 'resolve'])->whereNumber('anomaly');
    });

Route::prefix('v1')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:ai-management',
        'subscription.feature:anomaly.detection',
        'subscription.feature:ai.anomaly_explanation',
    ])
    ->group(function (): void {
        Route::post('/anomalies/{anomaly}/ai-explanation', [AiAnomalyExplanationController::class, 'store'])
            ->whereNumber('anomaly');
    });

// Callback OAuth dipanggil marketplace, bukan browser pengguna, jadi tanpa Sanctum.
// Keabsahannya dijamin state token sekali pakai.
Route::get('/v1/marketplaces/callback', MarketplaceCallbackController::class)
    ->middleware('throttle:30,1');

Route::prefix('v1/marketplaces')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:reconciliation-management',
        'subscription.feature:marketplace.integration',
    ])
    ->group(function (): void {
        Route::get('/', [MarketplaceConnectionController::class, 'index']);
        Route::post('/connect', [MarketplaceConnectionController::class, 'store']);
        Route::delete('/{connection}', [MarketplaceConnectionController::class, 'destroy'])->whereNumber('connection');

        Route::get('/orders', [MarketplaceOrderController::class, 'index']);
        Route::post('/{connection}/sync', [MarketplaceOrderController::class, 'sync'])->whereNumber('connection');
        Route::post('/{connection}/import', [MarketplaceOrderController::class, 'import'])->whereNumber('connection');
    });

Route::prefix('v1/ai/insights')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:ai-management',
        'subscription.feature:ai.insights',
    ])
    ->group(function (): void {
        Route::get('/', [AiInsightController::class, 'index']);
        Route::delete('/{insight}', [AiInsightController::class, 'destroy'])->whereNumber('insight');
        Route::post('/refresh', [AiInsightController::class, 'refresh'])
            ->middleware('subscription.feature:ai.on_demand_refresh');
    });

Route::prefix('v1')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:ai-management',
        'subscription.feature:ai.reminder_draft',
    ])
    ->group(function (): void {
        Route::post('/invoices/{invoice}/ai-reminder-draft', [AiReminderDraftController::class, 'store'])
            ->whereNumber('invoice');
    });

Route::prefix('v1')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:ai-management',
        'subscription.feature:reconciliation.automatic',
        'subscription.feature:ai.reconciliation',
    ])
    ->group(function (): void {
        Route::post('/bank-transactions/{bankTransaction}/ai-analysis', [AiReconciliationController::class, 'analyze'])
            ->whereNumber('bankTransaction');
    });

Route::prefix('v1')
    ->middleware([
        'auth:sanctum', 'verified', 'throttle:tax-report-management',
        'subscription.feature:tax.monthly_report',
    ])
    ->group(function (): void {
        Route::get('/tax-profile', [TaxpayerProfileController::class, 'show']);
        Route::put('/tax-profile', [TaxpayerProfileController::class, 'update']);

        Route::get('/tax-reports/overview', [TaxReportController::class, 'overview']);
        Route::get('/tax-reports/monthly', [TaxReportController::class, 'monthly']);
        Route::get('/tax-reports/{year}/annual-pdf', [TaxReportController::class, 'annualPdf'])
            ->middleware('subscription.feature:tax.annual_report')->whereNumber('year');
        Route::get('/tax-reports/{year}/{month}', [TaxReportController::class, 'show'])->whereNumber(['year', 'month']);
        Route::post('/tax-reports/{year}/{month}/recalculate', [TaxReportController::class, 'recalculate'])->whereNumber(['year', 'month']);
        Route::post('/tax-reports/{year}/{month}/finalize', [TaxReportController::class, 'finalize'])->whereNumber(['year', 'month']);
        Route::post('/tax-reports/{year}/{month}/mark-reported', [TaxReportController::class, 'markReported'])->whereNumber(['year', 'month']);
        Route::get('/tax-reports/{year}/{month}/pdf', [TaxReportController::class, 'monthlyPdf'])->whereNumber(['year', 'month']);

        Route::middleware('subscription.feature:tax.automated_audit')->group(function (): void {
            Route::get('/tax-audit-findings', [TaxAuditFindingController::class, 'index']);
            Route::post('/tax-audit-findings/{finding}/include', [TaxAuditFindingController::class, 'include'])->whereNumber('finding');
            Route::post('/tax-audit-findings/{finding}/resolve', [TaxAuditFindingController::class, 'resolve'])->whereNumber('finding');
        });
    });

Route::prefix('v1/admin/blogs')
    ->middleware(['auth:sanctum', 'verified', 'can:manage-blog', 'throttle:blog-management'])
    ->group(function (): void {
        Route::get('/', [BlogManagementController::class, 'index']);
        Route::post('/', [BlogManagementController::class, 'store']);
        Route::get('/{blogPost}', [BlogManagementController::class, 'show'])->whereNumber('blogPost');
        Route::match(['put', 'patch'], '/{blogPost}', [BlogManagementController::class, 'update'])->whereNumber('blogPost');
        Route::delete('/{blogPost}', [BlogManagementController::class, 'destroy'])->whereNumber('blogPost');
    });

Route::prefix('v1/blogs')
    ->middleware('throttle:blog-public')
    ->group(function (): void {
        Route::get('/', [BlogController::class, 'index']);
        Route::get('/{slug}', [BlogController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    });

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');

    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:auth-verification-resend');

    Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:auth-password-forgot');

    Route::get('/password/verify', [PasswordResetController::class, 'verify'])
        ->middleware('throttle:auth-password-reset');

    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth-password-reset');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->whereNumber('id')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->middleware('verified');
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
