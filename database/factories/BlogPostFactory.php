<?php

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'excerpt' => fake()->paragraph(),
            'content' => [
                [
                    'heading' => null,
                    'paragraphs' => [fake()->paragraphs(3, true)],
                    'bullets' => [],
                ],
            ],
            'category' => fake()->randomElement([
                'Tips Keuangan',
                'Panduan Pajak',
                'Cerita Sukses',
                'Update Fitur',
            ]),
            'cover_image_url' => '/images/blog-placeholder.png',
            'cover_image_alt' => 'Ilustrasi artikel InvoiceHub',
            'author_name' => 'Tim InvoiceHub',
            'reading_time_minutes' => fake()->numberBetween(3, 12),
            'is_featured' => false,
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
