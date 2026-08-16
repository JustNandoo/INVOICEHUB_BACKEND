<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->string('source', 30)->default('manual')->after('method');
            $table->timestampTz('voided_at')->nullable()->after('notes');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->index(['invoice_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->dropIndex(['invoice_id', 'voided_at']);
            $table->dropColumn(['source', 'voided_at', 'void_reason']);
        });
    }
};
