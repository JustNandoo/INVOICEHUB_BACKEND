<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('entry_type', 30)->default('revenue');
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('recorded');
            $table->boolean('is_taxable')->default(true);
            $table->timestampTz('recognized_at');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'source_type', 'source_id']);
            $table->index(['user_id', 'status', 'recognized_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_ledger_entries');
    }
};
