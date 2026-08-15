<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_only_published_articles(): void
    {
        BlogPost::factory()->count(3)->create();
        BlogPost::factory()->draft()->create();
        BlogPost::factory()->create(['published_at' => now()->addDay()]);

        $this->getJson('/api/v1/blogs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.articles')
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonStructure([
                'data' => [
                    'articles' => [[
                        'id',
                        'slug',
                        'title',
                        'excerpt',
                        'category',
                        'coverImageUrl',
                        'coverImageAlt',
                        'authorName',
                        'readingTimeMinutes',
                        'isFeatured',
                        'publishedAt',
                    ]],
                    'categories',
                    'pagination',
                ],
            ]);
    }

    public function test_guest_can_filter_articles_by_search_category_and_featured_status(): void
    {
        BlogPost::factory()->featured()->create([
            'title' => 'Panduan Pajak untuk UMKM',
            'excerpt' => 'Pelaporan pajak yang mudah.',
            'category' => 'Panduan Pajak',
        ]);
        BlogPost::factory()->create([
            'title' => 'Mengelola Arus Kas',
            'category' => 'Tips Keuangan',
        ]);

        $this->getJson('/api/v1/blogs?search=pajak&category=Panduan%20Pajak&featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles')
            ->assertJsonPath('data.articles.0.title', 'Panduan Pajak untuk UMKM')
            ->assertJsonPath('data.articles.0.isFeatured', true);
    }

    public function test_guest_can_open_published_article_detail_by_slug(): void
    {
        $post = BlogPost::factory()->create([
            'slug' => 'cara-mengelola-invoice',
            'content' => [
                [
                    'heading' => 'Mulai dari data yang rapi',
                    'paragraphs' => ['Catat setiap invoice secara konsisten.'],
                    'bullets' => ['Gunakan nomor invoice unik.'],
                ],
            ],
        ]);

        $this->getJson("/api/v1/blogs/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article.slug', 'cara-mengelola-invoice')
            ->assertJsonPath('data.article.content.0.heading', 'Mulai dari data yang rapi')
            ->assertJsonPath('data.article.content.0.bullets.0', 'Gunakan nomor invoice unik.');
    }

    public function test_draft_and_unknown_articles_return_not_found(): void
    {
        $draft = BlogPost::factory()->draft()->create(['slug' => 'artikel-draft']);

        $this->getJson("/api/v1/blogs/{$draft->slug}")->assertNotFound();
        $this->getJson('/api/v1/blogs/artikel-tidak-ada')->assertNotFound();
    }

    public function test_list_filters_are_validated(): void
    {
        $this->getJson('/api/v1/blogs?perPage=100&featured=invalid&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['perPage', 'featured', 'page']);
    }

    public function test_blog_endpoints_do_not_require_bearer_token(): void
    {
        $post = BlogPost::factory()->create();

        $this->getJson('/api/v1/blogs')->assertOk();
        $this->getJson("/api/v1/blogs/{$post->slug}")->assertOk();
    }
}
