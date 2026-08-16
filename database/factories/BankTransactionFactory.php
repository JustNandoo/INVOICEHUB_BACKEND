<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BankTransaction> */
class BankTransactionFactory extends Factory
{
    public function definition(): array
    {
        $reference = 'TRF-'.Str::upper(Str::random(12));

        return [
            'user_id' => User::factory(),
            'bank_account_id' => BankAccount::factory(),
            'external_transaction_id' => $reference,
            'fingerprint' => hash('sha256', $reference),
            'type' => 'credit',
            'amount' => fake()->numberBetween(100000, 5000000),
            'sender_name' => fake()->name(),
            'description' => 'TRANSFER MASUK',
            'reference' => $reference,
            'transaction_at' => now(),
            'status' => BankTransaction::STATUS_UNMATCHED,
        ];
    }
}
