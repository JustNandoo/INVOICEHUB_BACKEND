<?php

namespace App\Http\Requests\Api\Blog;

use Illuminate\Validation\Rule;

class AdminListBlogPostsRequest extends ListBlogPostsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['nullable', Rule::in(['published', 'draft', 'scheduled'])],
        ];
    }
}
