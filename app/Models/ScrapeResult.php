<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\VectorDatabase;
use Illuminate\Support\Facades\Log;

class ScrapeResult extends Model
{
    protected $fillable = [
        'scrape_process_id',
        'title',
        'content',
        'source_url',
        'author',
        'user_id',
        'internal_links',
        'external_links',
    ];

    protected $casts = [
        'scrape_process_id' => 'integer',
        'user_id' => 'integer',
        'internal_links' => 'array',
        'external_links' => 'array',
    ];

    /**
     * Boot the model and set up event listeners.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Delete vectors when ScrapeResult is deleted
        static::deleting(function (ScrapeResult $scrapeResult) {
            if ($scrapeResult->source_url && $scrapeResult->user_id) {
                try {
                    $vectorDb = VectorDatabase::make();
                    $vectorDb->deleteVectorsByUrl($scrapeResult->source_url, $scrapeResult->user_id);
                } catch (\Exception $e) {
                    Log::error("Failed to delete vectors for ScrapeResult {$scrapeResult->id}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Get the scrape process that owns this result
     */
    public function scrapeProcess(): BelongsTo
    {
        return $this->belongsTo(ScrapeProcess::class);
    }

    /**
     * Get the user that owns this result
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the content type (always 'blog' for scraped content)
     */
    public function getContentTypeAttribute(): string
    {
        return 'blog';
    }

    /**
     * Convert to expected item format
     */
    public function toExpectedItemFormat(): array
    {
        return [
            'title' => $this->title ?: 'Untitled',
            'content' => $this->content,
            'content_type' => $this->content_type,
            'source_url' => $this->source_url,
            'author' => $this->author ?: '',
            'user_id' => (string) $this->user_id,
        ];
    }
}
