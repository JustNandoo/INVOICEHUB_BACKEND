<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_code' => 'CUST-'.fake()->unique()->numerify('######'),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'whatsapp' => $whatsapp = '+62812'.fake()->unique()->numerify('########'),
            'whatsapp_normalized' => Str::remove('+', $whatsapp),
            'city' => fake()->city(),
            'address' => fake()->address(),
            'source' => 'manual',
            'is_active' => true,
        ];
    }
}
