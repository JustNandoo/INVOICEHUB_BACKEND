<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'cover_image_url',
        'cover_image_path',
        'cover_image_alt',
        'author_name',
        'reading_time_minutes',
        'is_featured',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'reading_time_minutes' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
