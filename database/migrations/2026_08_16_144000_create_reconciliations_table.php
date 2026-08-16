<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('matched_by', 20);
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedBigInteger('applied_amount');
            $table->string('status', 20)->default('confirmed');
            $table->text('notes')->nullable();
            $table->timestampTz('confirmed_at');
            $table->timestampTz('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status', 'confirmed_at']);
            $table->index(['bank_transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
