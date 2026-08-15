<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BlogService
{
    /**
     * @param  array{search?: string|null, category?: string|null, featured?: bool|null, perPage?: int|null, page?: int|null}  $filters
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function paginatePublished(array $filters): LengthAwarePaginator
    {
        $query = BlogPost::query()
            ->published()
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
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        return $query->paginate(
            perPage: (int) ($filters['perPage'] ?? 9),
            page: (int) ($filters['page'] ?? 1),
        )->withQueryString();
    }

    public function findPublishedBySlug(string $slug): BlogPost
    {
        return BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    public function publishedCategories(): array
    {
        return BlogPost::query()
            ->published()
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }
}
