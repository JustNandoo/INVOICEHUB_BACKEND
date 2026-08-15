<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Blog\AdminListBlogPostsRequest;
use App\Http\Requests\Api\Blog\StoreBlogPostRequest;
use App\Http\Requests\Api\Blog\UpdateBlogPostRequest;
use App\Http\Resources\Api\BlogPostResource;
use App\Http\Resources\Api\BlogPostSummaryResource;
use App\Models\BlogPost;
use App\Services\Blog\BlogManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class BlogManagementController extends Controller
{
    public function __construct(
        private readonly BlogManagementService $blogs,
    ) {}

    public function index(AdminListBlogPostsRequest $request): JsonResponse
    {
        $posts = $this->blogs->paginate($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'articles' => BlogPostSummaryResource::collection($posts->items())->resolve($request),
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

    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        /** @var UploadedFile $coverImage */
        $coverImage = $request->file('coverImage');
        $post = $this->blogs->create($request->validated(), $coverImage);

        return response()->json([
            'success' => true,
            'message' => 'Artikel blog berhasil dibuat.',
            'data' => [
                'article' => (new BlogPostResource($post))->resolve($request),
            ],
        ], 201);
    }

    public function show(Request $request, BlogPost $blogPost): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'article' => (new BlogPostResource($blogPost))->resolve($request),
            ],
        ]);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blogPost): JsonResponse
    {
        $coverImage = $request->file('coverImage');
        $post = $this->blogs->update(
            $blogPost,
            $request->validated(),
            $coverImage instanceof UploadedFile ? $coverImage : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Artikel blog berhasil diperbarui.',
            'data' => [
                'article' => (new BlogPostResource($post))->resolve($request),
            ],
        ]);
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $this->blogs->delete($blogPost);

        return response()->json([
            'success' => true,
            'message' => 'Artikel blog berhasil dihapus.',
        ]);
    }
}
