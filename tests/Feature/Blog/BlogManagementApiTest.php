<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_management_requires_verified_admin_account(): void
    {
        $this->getJson('/api/v1/admin/blogs')->assertUnauthorized();

        $regularUser = User::factory()->create();
        $regularToken = $regularUser->createToken('test')->plainTextToken;

        $this->withToken($regularToken)
            ->getJson('/api/v1/admin/blogs')
            ->assertForbidden();

        $unverifiedAdmin = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);
        $unverifiedToken = $unverifiedAdmin->createToken('test')->plainTextToken;

        $this->withToken($unverifiedToken)
            ->getJson('/api/v1/admin/blogs')
            ->assertForbidden();
    }

    public function test_admin_can_list_and_view_draft_articles(): void
    {
        $draft = BlogPost::factory()->draft()->create();
        BlogPost::factory()->create();

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/blogs?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles')
            ->assertJsonPath('data.articles.0.id', $draft->id)
            ->assertJsonPath('data.articles.0.status', 'draft');

        $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/blogs/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('data.article.id', $draft->id);
    }

    public function test_admin_can_create_blog_article_with_cover_image(): void
    {
        Storage::fake('public');

        $response = $this->withToken($this->adminToken())
            ->post('/api/v1/admin/blogs', $this->articlePayload(), [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article.title', 'Panduan Mengelola Invoice')
            ->assertJsonPath('data.article.slug', 'panduan-mengelola-invoice')
            ->assertJsonPath('data.article.status', 'draft');

        $post = BlogPost::query()->firstOrFail();

        $this->assertNotNull($post->cover_image_path);
        Storage::disk('public')->assertExists($post->cover_image_path);
    }

    public function test_admin_can_update_and_publish_article(): void
    {
        $post = BlogPost::factory()->draft()->create([
            'slug' => 'artikel-lama',
            'title' => 'Artikel Lama',
        ]);

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/admin/blogs/{$post->id}", [
                'title' => 'Artikel yang Diperbarui',
                'slug' => 'artikel-yang-diperbarui',
                'publishedAt' => now()->subMinute()->toIso8601String(),
                'isFeatured' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.article.title', 'Artikel yang Diperbarui')
            ->assertJsonPath('data.article.slug', 'artikel-yang-diperbarui')
            ->assertJsonPath('data.article.status', 'published')
            ->assertJsonPath('data.article.isFeatured', true);
    }

    public function test_admin_can_replace_cover_image_and_old_file_is_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blog/covers/old.jpg', 'old-image');
        $post = BlogPost::factory()->create([
            'cover_image_path' => 'blog/covers/old.jpg',
            'cover_image_url' => '/storage/blog/covers/old.jpg',
        ]);

        $this->withToken($this->adminToken())
            ->post("/api/v1/admin/blogs/{$post->id}", [
                '_method' => 'PATCH',
                'coverImage' => UploadedFile::fake()->image('new-cover.jpg', 1200, 800),
                'coverImageAlt' => 'Cover artikel baru',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $post->refresh();
        Storage::disk('public')->assertMissing('blog/covers/old.jpg');
        Storage::disk('public')->assertExists($post->cover_image_path);
    }

    public function test_admin_can_delete_article_and_managed_cover_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blog/covers/delete.jpg', 'image');
        $post = BlogPost::factory()->create([
            'cover_image_path' => 'blog/covers/delete.jpg',
        ]);

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/blogs/{$post->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
        Storage::disk('public')->assertMissing('blog/covers/delete.jpg');
    }

    public function test_create_article_validates_content_and_cover_image(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/blogs', [
                'title' => 'Artikel Tidak Lengkap',
                'content' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'excerpt',
                'category',
                'authorName',
                'readingTimeMinutes',
                'coverImage',
                'coverImageAlt',
                'content',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(): array
    {
        return [
            'title' => 'Panduan Mengelola Invoice',
            'excerpt' => 'Panduan lengkap mengelola invoice untuk UMKM.',
            'category' => 'Tips Keuangan',
            'authorName' => 'Admin InvoiceHub',
            'readingTimeMinutes' => 5,
            'isFeatured' => false,
            'publishedAt' => null,
            'coverImage' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            'coverImageAlt' => 'Ilustrasi pengelolaan invoice',
            'content' => [
                [
                    'heading' => 'Mulai dari data pelanggan',
                    'paragraphs' => ['Pastikan data pelanggan sudah lengkap.'],
                    'bullets' => ['Nama', 'Email', 'Nomor WhatsApp'],
                ],
            ],
        ];
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        return $admin->createToken('test')->plainTextToken;
    }
}
