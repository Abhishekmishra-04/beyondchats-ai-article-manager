<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the articles table.
 * 
 * This table stores both original scraped articles and their AI-updated versions.
 * We keep both versions to allow comparison and maintain data integrity.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the articles table with all necessary columns for storing
     * blog article data including original and AI-processed content.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            // Primary key - auto-incrementing ID
            $table->id();
            
            // Article title - required, max 255 chars for SEO best practices
            $table->string('title');
            
            // URL-friendly slug for clean URLs (e.g., /articles/my-first-post)
            // Unique constraint prevents duplicate slugs
            $table->string('slug')->unique();
            
            // Main article content - using longText for articles of any length
            // longText supports up to 4GB of text in MySQL
            $table->longText('content');
            
            // Original source URL from beyondchats.com
            // Nullable in case we later add articles manually
            $table->string('original_url')->nullable();
            
            // Flag to indicate if this article has been processed by AI
            // Defaults to false - set to true after AI rewriting
            $table->boolean('is_ai_updated')->default(false);
            
            // Store the AI-rewritten content separately
            // This preserves the original content for comparison
            $table->longText('ai_content')->nullable();
            
            // Citations/references added by AI (stored as JSON array)
            $table->json('citations')->nullable();
            
            // Timestamp when article was originally scraped
            $table->timestamp('scraped_at')->nullable();
            
            // Laravel's created_at and updated_at timestamps
            $table->timestamps();
            
            // Soft deletes - articles aren't permanently deleted
            // This allows recovery and maintains data history
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Drops the articles table if we need to rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
