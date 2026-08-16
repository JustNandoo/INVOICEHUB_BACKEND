<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['invoice_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_activities');
    }
};
