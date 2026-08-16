<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_period_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rule_version_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('total_revenue')->default(0);
            $table->unsignedBigInteger('taxable_revenue')->default(0);
            $table->unsignedBigInteger('pending_revenue')->default(0);
            $table->unsignedInteger('paid_invoice_count')->default(0);
            $table->unsignedInteger('tax_rate_basis_points');
            $table->unsignedBigInteger('estimated_tax')->default(0);
            $table->unsignedInteger('findings_count')->default(0);
            $table->json('calculation_metadata')->nullable();
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('reported_at')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->char('locked_hash', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'year', 'month']);
            $table->index(['user_id', 'status', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_period_reports');
    }
};
