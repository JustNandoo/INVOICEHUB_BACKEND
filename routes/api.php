<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Blog\BlogController;
use App\Http\Controllers\Api\Blog\BlogManagementController;
use App\Http\Controllers\Api\Customer\CustomerSearchController;
use App\Http\Controllers\Api\Invoice\InvoiceController;
use App\Http\Controllers\Api\Reconciliation\BankAccountController;
use App\Http\Controllers\Api\Reconciliation\BankTransactionController;
use App\Http\Controllers\Api\Reconciliation\BankTransactionImportController;
use App\Http\Controllers\Api\Reconciliation\ReconciliationController;
use App\Http\Controllers\Api\Tax\TaxAuditFindingController;
use App\Http\Controllers\Api\Tax\TaxpayerProfileController;
use App\Http\Controllers\Api\Tax\TaxReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'verified', 'throttle:invoice-management'])
    ->group(function (): void {
        Route::get('/customers', CustomerSearchController::class);

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

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'verified', 'throttle:reconciliation-management'])
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

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'verified', 'throttle:tax-report-management'])
    ->group(function (): void {
        Route::get('/tax-profile', [TaxpayerProfileController::class, 'show']);
        Route::put('/tax-profile', [TaxpayerProfileController::class, 'update']);

        Route::get('/tax-reports/overview', [TaxReportController::class, 'overview']);
        Route::get('/tax-reports/monthly', [TaxReportController::class, 'monthly']);
        Route::get('/tax-reports/{year}/annual-pdf', [TaxReportController::class, 'annualPdf'])->whereNumber('year');
        Route::get('/tax-reports/{year}/{month}', [TaxReportController::class, 'show'])->whereNumber(['year', 'month']);
        Route::post('/tax-reports/{year}/{month}/recalculate', [TaxReportController::class, 'recalculate'])->whereNumber(['year', 'month']);
        Route::post('/tax-reports/{year}/{month}/finalize', [TaxReportController::class, 'finalize'])->whereNumber(['year', 'month']);
        Route::post('/tax-reports/{year}/{month}/mark-reported', [TaxReportController::class, 'markReported'])->whereNumber(['year', 'month']);
        Route::get('/tax-reports/{year}/{month}/pdf', [TaxReportController::class, 'monthlyPdf'])->whereNumber(['year', 'month']);

        Route::get('/tax-audit-findings', [TaxAuditFindingController::class, 'index']);
        Route::post('/tax-audit-findings/{finding}/include', [TaxAuditFindingController::class, 'include'])->whereNumber('finding');
        Route::post('/tax-audit-findings/{finding}/resolve', [TaxAuditFindingController::class, 'resolve'])->whereNumber('finding');
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

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->whereNumber('id')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->middleware('verified');
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
