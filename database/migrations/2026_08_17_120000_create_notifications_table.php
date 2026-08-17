<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 100);
            $table->morphs('notifiable');
            $table->string('dedupe_key', 191);
            $table->json('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['notifiable_type', 'notifiable_id', 'dedupe_key'],
                'notifications_notifiable_dedupe_unique',
            );
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_inbox_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
