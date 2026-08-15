<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Blog\ListBlogPostsRequest;
use App\Http\Resources\Api\BlogPostResource;
use App\Http\Resources\Api\BlogPostSummaryResource;
use App\Services\Blog\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(
        private readonly BlogService $blogs,
    ) {}

    public function index(ListBlogPostsRequest $request): JsonResponse
    {
        $posts = $this->blogs->paginatePublished($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'articles' => BlogPostSummaryResource::collection($posts->items())->resolve($request),
                'categories' => $this->blogs->publishedCategories(),
                'pagination' => [
                    'currentPage' => $posts->currentPage(),
                    'perPage' => $posts->perPage(),
                    'lastPage' => $posts->lastPage(),
                    'total' => $posts->total(),
                    'from' => $posts->firstItem(),
                    'to' => $posts->lastItem(),
                    'previousPageUrl' => $posts->previousPageUrl(),
                    'nextPageUrl' => $posts->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $post = $this->blogs->findPublishedBySlug($slug);

        return response()->json([
            'success' => true,
            'data' => [
                'article' => (new BlogPostResource($post))->resolve($request),
            ],
        ]);
    }
}
