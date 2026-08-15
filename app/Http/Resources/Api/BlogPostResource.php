<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class BlogPostResource extends BlogPostSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'content' => $this->content,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
