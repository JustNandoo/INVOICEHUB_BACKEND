<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_suggestions', function (Blueprint $table): void {
            $table->foreignId('ai_run_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('ai_rank')->nullable()->after('ai_run_id');
            $table->unsignedTinyInteger('ai_confidence')->nullable()->after('ai_rank');
            $table->json('ai_reasons')->nullable()->after('ai_confidence');
            $table->boolean('ai_requires_review')->default(false)->after('ai_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_suggestions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_run_id');
            $table->dropColumn(['ai_rank', 'ai_confidence', 'ai_reasons', 'ai_requires_review']);
        });
    }
};
