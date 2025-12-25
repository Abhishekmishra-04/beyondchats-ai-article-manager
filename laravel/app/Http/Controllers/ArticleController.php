<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * ArticleController - RESTful CRUD API
 * 
 * This controller handles all article-related API endpoints.
 * It follows REST conventions:
 * 
 *   GET    /api/articles          → index()   - List all articles
 *   POST   /api/articles          → store()   - Create new article
 *   GET    /api/articles/{id}     → show()    - Get single article
 *   PUT    /api/articles/{id}     → update()  - Update article
 *   DELETE /api/articles/{id}     → destroy() - Delete article
 * 
 * Design Principles:
 * - Single Responsibility: Each method does one thing
 * - Consistent response format using API Resources
 * - Comprehensive validation with clear error messages
 * - Proper HTTP status codes
 */
class ArticleController extends Controller
{
    /**
     * Display a listing of articles.
     * 
     * GET /api/articles
     * 
     * Query Parameters:
     *   - per_page: Number of items per page (default: 15)
     *   - ai_updated: Filter by AI status ('true', 'false', or omit for all)
     *   - sort: Sort field (default: 'created_at')
     *   - order: Sort direction ('asc' or 'desc', default: 'desc')
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Build query with optional filters
        $query = Article::query();

        // Filter by AI update status if specified
        if ($request->has('ai_updated')) {
            $isAiUpdated = filter_var($request->ai_updated, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_ai_updated', $isAiUpdated);
        }

        // Search by title if specified
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sorting - default to newest first
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        
        // Only allow sorting by specific fields (security)
        $allowedSortFields = ['created_at', 'updated_at', 'title', 'scraped_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        // Paginate results
        $perPage = min((int) $request->get('per_page', 15), 100); // Max 100 per page
        $articles = $query->paginate($perPage);

        // Return using API Resource for consistent formatting
        return response()->json([
            'success' => true,
            'data' => ArticleResource::collection($articles),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    /**
     * Store a newly created article.
     * 
     * POST /api/articles
     * 
     * Request Body (JSON):
     * {
     *   "title": "Article Title",         // Required, max 255 chars
     *   "content": "Article body...",     // Required
     *   "slug": "article-title",          // Optional, auto-generated if omitted
     *   "original_url": "https://...",    // Optional
     *   "is_ai_updated": false            // Optional, default false
     * }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validate incoming data
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:articles,slug',
            'content' => 'required|string|min:10',
            'original_url' => 'nullable|url|max:2048',
            'is_ai_updated' => 'boolean',
            'ai_content' => 'nullable|string',
            'citations' => 'nullable|array',
            'citations.*' => 'string|url', // Each citation must be a valid URL
        ], [
            // Custom error messages for better UX
            'title.required' => 'Please provide an article title.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'content.required' => 'Article content is required.',
            'content.min' => 'Article content must be at least 10 characters.',
            'original_url.url' => 'Please provide a valid URL.',
            'slug.unique' => 'This slug is already in use.',
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422); // 422 Unprocessable Entity
        }

        // Create the article
        $article = Article::create($validator->validated());

        // Return the created article with 201 status
        return response()->json([
            'success' => true,
            'message' => 'Article created successfully',
            'data' => new ArticleResource($article),
        ], 201); // 201 Created
    }

    /**
     * Display the specified article.
     * 
     * GET /api/articles/{id}
     * 
     * The {id} can be either:
     *   - Numeric ID: /api/articles/1
     *   - Slug: /api/articles/my-article-title
     * 
     * @param string $id Article ID or slug
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        // Find by ID or slug for flexibility
        $article = Article::where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        // Return 404 if not found
        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ArticleResource($article),
        ]);
    }

    /**
     * Get the latest article (for Node.js AI script).
     * 
     * GET /api/articles/latest
     * 
     * Returns the most recently created article that hasn't been AI-updated yet.
     * This endpoint is specifically designed for the AI rewriting workflow.
     * 
     * @return JsonResponse
     */
    public function latest(): JsonResponse
    {
        $article = Article::where('is_ai_updated', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'No articles pending AI update',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ArticleResource($article),
        ]);
    }

    /**
     * Update the specified article.
     * 
     * PUT /api/articles/{id}
     * 
     * All fields are optional - only provided fields are updated.
     * This is called "PATCH-like behavior" on a PUT endpoint.
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Find the article
        $article = Article::where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        // Validate - note the 'sometimes' rule means field is only validated if present
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            // Slug must be unique, but ignore current article's slug
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('articles')->ignore($article->id),
            ],
            'content' => 'sometimes|required|string|min:10',
            'original_url' => 'nullable|url|max:2048',
            'is_ai_updated' => 'boolean',
            'ai_content' => 'nullable|string',
            'citations' => 'nullable|array',
            'citations.*' => 'string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update only the provided fields
        $article->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Article updated successfully',
            'data' => new ArticleResource($article),
        ]);
    }

    /**
     * Remove the specified article.
     * 
     * DELETE /api/articles/{id}
     * 
     * Uses soft delete - the article is marked as deleted but not removed.
     * This allows for recovery if needed.
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $article = Article::where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        // Soft delete
        $article->delete();

        // 204 No Content is standard for successful DELETE
        // But we return 200 with message for better client feedback
        return response()->json([
            'success' => true,
            'message' => 'Article deleted successfully',
        ]);
    }

    /**
     * Publish AI-updated content for an article.
     * 
     * POST /api/articles/{id}/publish-ai
     * 
     * This is a custom endpoint for the AI workflow.
     * It updates the article with AI-generated content and citations.
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function publishAi(Request $request, string $id): JsonResponse
    {
        $article = Article::where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'ai_content' => 'required|string|min:50',
            'citations' => 'required|array|min:1',
            'citations.*' => 'string|url',
        ], [
            'ai_content.required' => 'AI-generated content is required.',
            'ai_content.min' => 'AI content seems too short. Please provide meaningful content.',
            'citations.required' => 'At least one citation is required.',
            'citations.min' => 'At least one citation is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update with AI content
        $article->update([
            'ai_content' => $request->ai_content,
            'citations' => $request->citations,
            'is_ai_updated' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI content published successfully',
            'data' => new ArticleResource($article),
        ]);
    }
}
