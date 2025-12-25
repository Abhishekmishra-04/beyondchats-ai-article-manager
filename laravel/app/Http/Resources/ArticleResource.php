<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ArticleResource - API Response Transformer
 * 
 * API Resources transform your models into JSON responses.
 * 
 * Benefits:
 * - Consistent response structure across all endpoints
 * - Control exactly which fields are exposed (security)
 * - Format data appropriately (dates, computed fields, etc.)
 * - Hide internal implementation details from API consumers
 * 
 * Usage:
 *   return new ArticleResource($article);
 *   return ArticleResource::collection($articles);
 */
class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * This method defines the structure of the JSON response.
     * The $this variable refers to the Article model being transformed.
     * 
     * @param Request $request The incoming HTTP request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic identifiers
            'id' => $this->id,
            'slug' => $this->slug,
            
            // Content fields
            'title' => $this->title,
            'content' => $this->content,
            
            // Source information
            'original_url' => $this->original_url,
            
            // AI-related fields
            'is_ai_updated' => $this->is_ai_updated,
            'ai_content' => $this->ai_content,
            'citations' => $this->citations ?? [],
            
            // Computed field - returns the most appropriate content
            // This saves the frontend from having to implement this logic
            'display_content' => $this->display_content,
            
            // Timestamps - formatted for readability
            // Using ISO 8601 format which is JavaScript-friendly
            'scraped_at' => $this->scraped_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Content statistics - useful for UI display
            'meta' => [
                'word_count' => str_word_count($this->content ?? ''),
                'ai_word_count' => $this->ai_content 
                    ? str_word_count($this->ai_content) 
                    : null,
                'citation_count' => count($this->citations ?? []),
                'has_ai_version' => $this->is_ai_updated && !empty($this->ai_content),
            ],

            // API links for easy navigation (HATEOAS principle)
            // This helps API consumers discover related endpoints
            'links' => [
                'self' => url("/api/articles/{$this->slug}"),
                'update' => url("/api/articles/{$this->slug}"),
                'delete' => url("/api/articles/{$this->slug}"),
                'publish_ai' => url("/api/articles/{$this->slug}/publish-ai"),
            ],
        ];
    }

    /**
     * Customize the response when using with() method.
     * 
     * This adds extra data to the JSON response wrapper.
     * 
     * Example response:
     * {
     *   "data": { ... article data ... },
     *   "api_version": "1.0"
     * }
     * 
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'api_version' => '1.0',
        ];
    }
}
