<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankAccount> */
class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numerify('##########');

        return [
            'user_id' => User::factory(),
            'bank_code' => fake()->randomElement(['BCA', 'BNI', 'BRI', 'MANDIRI']),
            'bank_name' => 'Bank '.fake()->company(),
            'account_holder' => fake()->name(),
            'account_number' => $number,
            'account_number_last_four' => substr($number, -4),
            'account_number_fingerprint' => hash_hmac('sha256', $number, 'factory-key'),
            'balance' => fake()->numberBetween(1000000, 100000000),
            'connection_status' => 'connected',
            'last_synced_at' => now()->subMinutes(10),
        ];
    }
}
