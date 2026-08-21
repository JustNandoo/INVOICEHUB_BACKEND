<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->string('title', 150);
            $table->text('description');
            $table->unsignedBigInteger('amount_at_risk')->default(0);
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->json('metadata')->nullable();

            // Filled only when the user asks AI to explain this specific anomaly.
            $table->foreignId('ai_run_id')->nullable()->constrained()->nullOnDelete();
            $table->text('explanation')->nullable();
            $table->text('likely_cause')->nullable();
            $table->text('prevention_tip')->nullable();
            $table->string('recommended_action', 60)->nullable();
            $table->string('action_url', 255)->nullable();
            $table->timestampTz('explained_at')->nullable();

            $table->timestampTz('detected_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolution', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            // Re-scanning must never duplicate a finding.
            $table->unique(['user_id', 'type', 'source_type', 'source_id'], 'financial_anomalies_source_unique');
            $table->index(['user_id', 'status', 'severity']);
            $table->index(['user_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_anomalies');
    }
};
