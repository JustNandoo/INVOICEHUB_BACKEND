<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepted')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->json('corrected_value')->nullable();
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->unique(['ai_run_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
