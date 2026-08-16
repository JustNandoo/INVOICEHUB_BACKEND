<?php

namespace App\Services\Invoice;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function next(User $user, int $year): string
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        $sequence = DB::table('invoice_number_sequences')
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            DB::table('invoice_number_sequences')->insert([
                'user_id' => $user->id,
                'year' => $year,
                'last_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $next = 1;
        } else {
            $next = ((int) $sequence->last_number) + 1;
            DB::table('invoice_number_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);
        }

        return sprintf('INV-%d-%03d', $year, $next);
    }
}
