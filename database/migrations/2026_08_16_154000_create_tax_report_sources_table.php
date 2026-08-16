<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_report_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_period_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('amount');
            $table->timestampTz('recognized_at');
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['tax_period_report_id', 'source_type', 'source_id'], 'tax_report_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_report_sources');
    }
};
