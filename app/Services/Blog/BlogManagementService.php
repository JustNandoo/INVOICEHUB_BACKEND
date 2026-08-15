<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BlogManagementService
{
    /**
     * @param  array{search?: string|null, category?: string|null, featured?: bool|null, status?: string|null, perPage?: int|null, page?: int|null}  $filters
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return BlogPost::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('title', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('excerpt', "%{$search}%", caseSensitive: false);
                }),
            )
            ->when(
                $filters['category'] ?? null,
                fn ($query, string $category) => $query->where('category', $category),
            )
            ->when(
                array_key_exists('featured', $filters) && $filters['featured'] !== null,
                fn ($query) => $query->where('is_featured', (bool) $filters['featured']),
            )
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                match ($status) {
                    'published' => $query->published(),
                    'draft' => $query->whereNull('published_at'),
                    'scheduled' => $query->where('published_at', '>', now()),
                };
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: (int) ($filters['perPage'] ?? 15),
                page: (int) ($filters['page'] ?? 1),
            )
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $coverImage): BlogPost
    {
        $coverImagePath = $this->storeCoverImage($coverImage);

        try {
            return DB::transaction(function () use ($data, $coverImagePath): BlogPost {
                $attributes = $this->mapAttributes($data);
                $attributes['slug'] = $this->uniqueSlug(
                    (string) ($data['slug'] ?? Str::slug((string) $data['title'])),
                );
                $attributes['cover_image_path'] = $coverImagePath;
                $attributes['cover_image_url'] = Storage::disk('public')->url($coverImagePath);

                return BlogPost::query()->create($attributes);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($coverImagePath);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BlogPost $post, array $data, ?UploadedFile $coverImage): BlogPost
    {
        $newCoverImagePath = $coverImage ? $this->storeCoverImage($coverImage) : null;
        $oldCoverImagePath = $post->cover_image_path;

        try {
            DB::transaction(function () use ($post, $data, $newCoverImagePath): void {
                $attributes = $this->mapAttributes($data);

                if (isset($data['slug'])) {
                    $attributes['slug'] = $data['slug'];
                }

                if ($newCoverImagePath) {
                    $attributes['cover_image_path'] = $newCoverImagePath;
                    $attributes['cover_image_url'] = Storage::disk('public')->url($newCoverImagePath);
                }

                $post->update($attributes);
            });
        } catch (Throwable $exception) {
            if ($newCoverImagePath) {
                Storage::disk('public')->delete($newCoverImagePath);
            }

            throw $exception;
        }

        if ($newCoverImagePath && $oldCoverImagePath) {
            Storage::disk('public')->delete($oldCoverImagePath);
        }

        return $post->refresh();
    }

    public function delete(BlogPost $post): void
    {
        $coverImagePath = $post->cover_image_path;

        DB::transaction(fn () => $post->delete());

        if ($coverImagePath) {
            Storage::disk('public')->delete($coverImagePath);
        }
    }

    private function storeCoverImage(UploadedFile $coverImage): string
    {
        $extension = strtolower($coverImage->extension() ?: 'jpg');
        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = $coverImage->storePubliclyAs('blog/covers', $fileName, 'public');

        if (! is_string($path)) {
            throw new RuntimeException('Gagal menyimpan cover image blog.');
        }

        return $path;
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $baseSlug = Str::slug($baseSlug) ?: Str::uuid()->toString();
        $slug = $baseSlug;
        $suffix = 2;

        while (BlogPost::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapAttributes(array $data): array
    {
        $fieldMap = [
            'title' => 'title',
            'excerpt' => 'excerpt',
            'content' => 'content',
            'category' => 'category',
            'coverImageAlt' => 'cover_image_alt',
            'authorName' => 'author_name',
            'readingTimeMinutes' => 'reading_time_minutes',
            'isFeatured' => 'is_featured',
            'publishedAt' => 'published_at',
        ];
        $attributes = [];

        foreach ($fieldMap as $requestField => $databaseField) {
            if (array_key_exists($requestField, $data)) {
                $attributes[$databaseField] = $data[$requestField];
            }
        }

        return $attributes;
    }
}
