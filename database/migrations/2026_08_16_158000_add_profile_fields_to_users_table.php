<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('whatsapp', 30)->nullable()->after('business_name');
            $table->string('whatsapp_normalized', 30)->nullable()->after('whatsapp');
            $table->string('city', 100)->nullable()->after('whatsapp_normalized');
            $table->string('business_type', 30)->nullable()->after('city');
            $table->string('avatar_path')->nullable()->after('business_type');
            $table->timestampTz('password_changed_at')->nullable()->after('password');

            $table->unique('whatsapp_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_normalized']);
            $table->dropColumn([
                'whatsapp', 'whatsapp_normalized', 'city', 'business_type',
                'avatar_path', 'password_changed_at',
            ]);
        });
    }
};
