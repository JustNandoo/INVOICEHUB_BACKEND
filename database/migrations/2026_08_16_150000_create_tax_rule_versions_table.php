<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('regulation');
            $table->unsignedInteger('rate_basis_points');
            $table->unsignedBigInteger('annual_revenue_limit')->nullable();
            $table->unsignedBigInteger('individual_non_taxable_threshold')->default(0);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rule_versions');
    }
};
