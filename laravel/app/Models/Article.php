<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Article Model
 * 
 * Represents a blog article in our database.
 * Contains both original scraped content and AI-processed versions.
 * 
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $original_url
 * @property bool $is_ai_updated
 * @property string|null $ai_content
 * @property array|null $citations
 * @property \Carbon\Carbon|null $scraped_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Article extends Model
{
    // Enables factory support for testing
    use HasFactory;
    
    // Enables soft deletes - records are marked deleted, not removed
    use SoftDeletes;

    /**
     * Mass-assignable attributes.
     * 
     * These fields can be filled using Article::create([...]) or $article->fill([...])
     * This is a security feature to prevent mass-assignment vulnerabilities.
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'original_url',
        'is_ai_updated',
        'ai_content',
        'citations',
        'scraped_at',
    ];

    /**
     * Attribute type casting.
     * 
     * Laravel automatically converts these attributes to the specified types.
     * - 'boolean' ensures is_ai_updated is always true/false, not 0/1
     * - 'array' automatically JSON encodes/decodes the citations field
     * - 'datetime' converts scraped_at to Carbon instance for date manipulation
     */
    protected $casts = [
        'is_ai_updated' => 'boolean',
        'citations' => 'array',
        'scraped_at' => 'datetime',
    ];

    /**
     * Boot method - runs when the model is initialized.
     * 
     * We use this to automatically generate slugs when creating articles.
     * This is called a "model event" - it hooks into the creation process.
     */
    protected static function boot()
    {
        parent::boot();

        // Before creating a new article, auto-generate slug if not provided
        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
        });
    }

    /**
     * Generate a unique slug from the title.
     * 
     * If "my-article" exists, it creates "my-article-1", "my-article-2", etc.
     * 
     * @param string $title The article title
     * @return string A unique slug
     */
    public static function generateUniqueSlug(string $title): string
    {
        // Str::slug() converts "My Article Title" to "my-article-title"
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        // Keep incrementing until we find an unused slug
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Scope to get only articles that have been AI-updated.
     * 
     * Usage: Article::aiUpdated()->get()
     * 
     * Scopes are reusable query constraints - they keep controllers clean.
     */
    public function scopeAiUpdated($query)
    {
        return $query->where('is_ai_updated', true);
    }

    /**
     * Scope to get only original (non-AI-updated) articles.
     * 
     * Usage: Article::original()->get()
     */
    public function scopeOriginal($query)
    {
        return $query->where('is_ai_updated', false);
    }

    /**
     * Scope to get the oldest articles first.
     * 
     * Usage: Article::oldest()->take(5)->get()
     */
    public function scopeOldestFirst($query)
    {
        return $query->orderBy('scraped_at', 'asc');
    }

    /**
     * Get the display content - returns AI content if available, otherwise original.
     * 
     * This is an "accessor" - it creates a virtual attribute.
     * Access it like: $article->display_content
     */
    public function getDisplayContentAttribute(): string
    {
        return $this->is_ai_updated && $this->ai_content 
            ? $this->ai_content 
            : $this->content;
    }
}
