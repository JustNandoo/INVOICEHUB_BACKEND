<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('draft');

            $table->string('issuer_name', 150);
            $table->string('issuer_email')->nullable();
            $table->text('issuer_address')->nullable();
            $table->string('customer_name', 150);
            $table->string('customer_email')->nullable();
            $table->string('customer_whatsapp', 30)->nullable();
            $table->text('customer_address')->nullable();

            $table->date('issue_date');
            $table->date('due_date');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedInteger('tax_rate_basis_points')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('balance_due')->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('viewed_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'issue_date']);
            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
