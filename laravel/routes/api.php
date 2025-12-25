<?php

use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you register API routes for your application.
| These routes are loaded by the RouteServiceProvider and are assigned
| the "api" middleware group (which includes rate limiting, etc.).
|
| All routes here are automatically prefixed with /api
| So '/articles' becomes '/api/articles'
|
*/

/*
|--------------------------------------------------------------------------
| Article Routes
|--------------------------------------------------------------------------
|
| RESTful routes for article CRUD operations.
| 
| Route List:
|   GET    /api/articles              - List all articles (paginated)
|   POST   /api/articles              - Create a new article
|   GET    /api/articles/latest       - Get latest article for AI processing
|   GET    /api/articles/{id}         - Get a specific article
|   PUT    /api/articles/{id}         - Update an article
|   DELETE /api/articles/{id}         - Delete an article
|   POST   /api/articles/{id}/publish-ai - Publish AI-updated content
|
*/

// Group all article routes together for organization
Route::prefix('articles')->group(function () {
    
    // List all articles with pagination and filtering
    // Example: GET /api/articles?per_page=10&ai_updated=true
    Route::get('/', [ArticleController::class, 'index'])
        ->name('articles.index');
    
    // Get the latest article that hasn't been AI-updated
    // Used by the Node.js AI script to find work
    // IMPORTANT: This must come BEFORE the {id} route!
    // Otherwise 'latest' would be treated as an ID
    Route::get('/latest', [ArticleController::class, 'latest'])
        ->name('articles.latest');
    
    // Create a new article
    // Expects JSON body with title, content, etc.
    Route::post('/', [ArticleController::class, 'store'])
        ->name('articles.store');
    
    // Get a single article by ID or slug
    // Example: GET /api/articles/1 or GET /api/articles/my-article-slug
    Route::get('/{id}', [ArticleController::class, 'show'])
        ->name('articles.show');
    
    // Update an article
    // Example: PUT /api/articles/1 with JSON body
    Route::put('/{id}', [ArticleController::class, 'update'])
        ->name('articles.update');
    
    // Also support PATCH for partial updates (same handler)
    Route::patch('/{id}', [ArticleController::class, 'update']);
    
    // Delete an article (soft delete)
    Route::delete('/{id}', [ArticleController::class, 'destroy'])
        ->name('articles.destroy');
    
    // Custom endpoint: Publish AI-updated content
    // This is separate from regular update for cleaner separation of concerns
    Route::post('/{id}/publish-ai', [ArticleController::class, 'publishAi'])
        ->name('articles.publish-ai');
});

/*
|--------------------------------------------------------------------------
| Health Check Route
|--------------------------------------------------------------------------
|
| A simple endpoint to verify the API is running.
| Useful for monitoring, load balancers, and deployment scripts.
|
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('health');

/*
|--------------------------------------------------------------------------
| API Documentation Route (Optional)
|--------------------------------------------------------------------------
|
| Returns basic API documentation.
| In production, you might use Swagger/OpenAPI instead.
|
*/
Route::get('/', function () {
    return response()->json([
        'name' => 'Articles API',
        'version' => '1.0.0',
        'description' => 'RESTful API for managing blog articles with AI enhancement',
        'endpoints' => [
            'articles' => [
                'list' => 'GET /api/articles',
                'create' => 'POST /api/articles',
                'read' => 'GET /api/articles/{id}',
                'update' => 'PUT /api/articles/{id}',
                'delete' => 'DELETE /api/articles/{id}',
                'latest' => 'GET /api/articles/latest',
                'publish_ai' => 'POST /api/articles/{id}/publish-ai',
            ],
            'health' => 'GET /api/health',
        ],
        'documentation' => 'See README.md for detailed API documentation',
    ]);
})->name('api.index');
