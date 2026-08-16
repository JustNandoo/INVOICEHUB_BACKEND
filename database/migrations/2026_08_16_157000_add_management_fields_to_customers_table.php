<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('customer_code', 30)->nullable()->after('user_id');
            $table->string('whatsapp_normalized', 30)->nullable()->after('whatsapp');
        });

        $seenWhatsapp = [];
        $lastNumbers = [];
        DB::table('customers')->orderBy('user_id')->orderBy('id')->get()->each(
            function (object $customer) use (&$seenWhatsapp, &$lastNumbers): void {
                $number = ($lastNumbers[$customer->user_id] ?? 0) + 1;
                $lastNumbers[$customer->user_id] = $number;
                $normalized = $this->normalizeWhatsapp($customer->whatsapp);
                $key = $customer->user_id.'|'.$normalized;
                if ($normalized !== null && isset($seenWhatsapp[$key])) {
                    $normalized = null;
                } elseif ($normalized !== null) {
                    $seenWhatsapp[$key] = true;
                }

                DB::table('customers')->where('id', $customer->id)->update([
                    'customer_code' => sprintf('CUST-%03d', $number),
                    'whatsapp_normalized' => $normalized,
                ]);
            },
        );

        foreach ($lastNumbers as $userId => $lastNumber) {
            DB::table('customer_number_sequences')->insert([
                'user_id' => $userId, 'last_number' => $lastNumber,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['user_id', 'customer_code']);
            $table->unique(['user_id', 'whatsapp_normalized']);
            $table->index(['user_id', 'is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'customer_code']);
            $table->dropUnique(['user_id', 'whatsapp_normalized']);
            $table->dropIndex(['user_id', 'is_active', 'created_at']);
            $table->dropColumn(['customer_code', 'whatsapp_normalized']);
        });
    }

    private function normalizeWhatsapp(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
};
