<?php

namespace App\Http\Requests\Api\Blog;

use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function blogPostRules(bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $uniqueSlug = Rule::unique('blog_posts', 'slug');
        $routePost = $this->route('blogPost');

        if ($routePost instanceof BlogPost) {
            $uniqueSlug->ignore($routePost);
        }

        return [
            'title' => [$required, 'string', 'max:180'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $uniqueSlug],
            'excerpt' => [$required, 'string', 'max:1000'],
            'category' => [$required, 'string', 'max:80'],
            'authorName' => [$required, 'string', 'max:100'],
            'readingTimeMinutes' => [$required, 'integer', 'min:1', 'max:120'],
            'isFeatured' => ['sometimes', 'boolean'],
            'publishedAt' => ['sometimes', 'nullable', 'date'],
            'coverImage' => [$required, 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'coverImageAlt' => [$required, 'string', 'max:180'],
            'content' => [$required, 'array', 'min:1', 'max:50'],
            'content.*.heading' => ['nullable', 'string', 'max:180'],
            'content.*.paragraphs' => ['required', 'array', 'min:1', 'max:30'],
            'content.*.paragraphs.*' => ['required', 'string', 'max:3000'],
            'content.*.bullets' => ['sometimes', 'array', 'max:20'],
            'content.*.bullets.*' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('content'))) {
            return;
        }

        $decodedContent = json_decode($this->string('content')->toString(), true);

        if (is_array($decodedContent)) {
            $this->merge(['content' => $decodedContent]);
        }
    }
}
