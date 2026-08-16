<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_period_report_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('type', 50);
            $table->string('severity', 20);
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('status', 20)->default('open');
            $table->string('recommended_action', 80)->nullable();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('resolution', 40)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'year', 'month', 'status']);
            $table->unique(['user_id', 'year', 'month', 'type', 'source_type', 'source_id'], 'tax_finding_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_audit_findings');
    }
};
