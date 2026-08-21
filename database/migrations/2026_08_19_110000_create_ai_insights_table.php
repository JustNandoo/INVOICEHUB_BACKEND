<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('severity', 20);
            $table->string('title', 150);
            $table->text('summary');
            $table->json('evidence');
            $table->string('recommended_action', 60);
            $table->string('action_url', 255)->nullable();
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestampTz('valid_until');
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'dismissed_at', 'valid_until']);
            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
