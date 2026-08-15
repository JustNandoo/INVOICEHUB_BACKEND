<?php

namespace App\Http\Requests\Api\Blog;

class UpdateBlogPostRequest extends BlogPostRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->blogPostRules(partial: true);
    }
}
