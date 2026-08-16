<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'bank_code', 'account_number_last_four']);
            $table->char('account_number_fingerprint', 64)->nullable()->after('account_number_last_four');
            $table->unique(['user_id', 'bank_code', 'account_number_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'bank_code', 'account_number_fingerprint']);
            $table->dropColumn('account_number_fingerprint');
            $table->unique(['user_id', 'bank_code', 'account_number_last_four']);
        });
    }
};
