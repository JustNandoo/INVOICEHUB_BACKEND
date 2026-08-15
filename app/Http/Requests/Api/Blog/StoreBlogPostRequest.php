<?php

namespace App\Http\Requests\Api\Blog;

class StoreBlogPostRequest extends BlogPostRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->blogPostRules(partial: false);
    }
}
