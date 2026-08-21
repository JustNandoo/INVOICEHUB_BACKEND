<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('provider', 20);
            $table->string('model', 80);
            $table->string('prompt_version', 20);
            $table->string('status', 20);
            $table->char('input_hash', 64);
            $table->json('structured_output')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('thinking_tokens')->default(0);
            $table->unsignedBigInteger('estimated_cost')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'feature', 'created_at']);
            $table->index(['feature', 'input_hash', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
