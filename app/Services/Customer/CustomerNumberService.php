<?php

namespace App\Services\Customer;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerNumberService
{
    public function next(User $user): string
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $sequence = DB::table('customer_number_sequences')->where('user_id', $user->id)->lockForUpdate()->first();

        if ($sequence === null) {
            $next = 1;
            DB::table('customer_number_sequences')->insert([
                'user_id' => $user->id, 'last_number' => $next,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $next = ((int) $sequence->last_number) + 1;
            DB::table('customer_number_sequences')->where('id', $sequence->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);
        }

        return sprintf('CUST-%03d', $next);
    }
}
