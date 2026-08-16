<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxpayer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rule_version_id')->constrained()->restrictOnDelete();
            $table->string('taxpayer_type', 30);
            $table->string('taxpayer_name');
            $table->string('business_name');
            $table->text('npwp')->nullable();
            $table->string('npwp_last_four', 4)->nullable();
            $table->char('npwp_fingerprint', 64)->nullable();
            $table->string('tax_scheme', 40)->default('final_umkm');
            $table->string('accounting_method', 20)->default('cash_basis');
            $table->date('effective_from');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxpayer_profiles');
    }
};
