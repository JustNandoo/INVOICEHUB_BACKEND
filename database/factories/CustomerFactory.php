<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'whatsapp' => '+62 812 '.fake()->numerify('#### ####'),
            'city' => fake()->city(),
            'address' => fake()->address(),
            'source' => 'manual',
            'is_active' => true,
        ];
    }
}
