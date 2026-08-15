<?php

namespace App\Http\Requests\Api\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ListBlogPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'featured' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:24'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('search')) {
            $normalized['search'] = Str::squish((string) $this->input('search')) ?: null;
        }

        if ($this->has('category')) {
            $normalized['category'] = Str::squish((string) $this->input('category')) ?: null;
        }

        $this->merge($normalized);
    }
}
