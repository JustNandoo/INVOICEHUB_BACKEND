<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_financial_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('ready');
            $table->json('metrics');
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'period_start', 'period_end'], 'weekly_reports_owner_period_unique');
            $table->index(['user_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_financial_reports');
    }
};
