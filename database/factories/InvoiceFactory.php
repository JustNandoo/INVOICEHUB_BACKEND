<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(100000, 10000000);

        return [
            'user_id' => User::factory(),
            'number' => 'INV-'.now()->year.'-'.fake()->unique()->numerify('###'),
            'status' => Invoice::STATUS_UNPAID,
            'issuer_name' => fake()->company(),
            'issuer_email' => fake()->companyEmail(),
            'customer_name' => fake()->company(),
            'customer_email' => fake()->companyEmail(),
            'customer_whatsapp' => '+62 812 '.fake()->numerify('#### ####'),
            'issue_date' => today(),
            'due_date' => today()->addMonth(),
            'subtotal' => $total,
            'tax_rate_basis_points' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'balance_due' => $total,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => Invoice::STATUS_DRAFT]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Invoice::STATUS_PAID,
            'paid_amount' => $attributes['total_amount'],
            'balance_due' => 0,
            'paid_at' => now(),
        ]);
    }
}
