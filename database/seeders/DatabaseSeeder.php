<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BlogPostSeeder::class);

        if ($this->container->environment('local')) {
            $this->call(DemoAccountSeeder::class);
        }
    }
}
