<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScrapeProcess extends Model
{
    protected $fillable = [
        'uuid',
        'url',
        'status',
        'user_id',
        'error_message',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Boot the model and generate UUID
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the scrape process
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all scrape results for this process
     */
    public function scrapeResults(): HasMany
    {
        return $this->hasMany(ScrapeResult::class);
    }

    /**
     * Mark the process as completed
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark the process as failed with error message
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Mark the process as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Convert to expected output format
     */
    public function toExpectedFormat(): array
    {
        return [
            'user_id' => (string) $this->user_id,
            'items' => $this->scrapeResults->map(function ($result) {
                return [
                    'title' => $result->title,
                    'content' => $result->content,
                    'content_type' => 'blog',
                    'source_url' => $result->source_url,
                    'author' => $result->author,
                    'user_id' => (string) $result->user_id,
                ];
            })->toArray(),
        ];
    }

    /**
     * Check if the process is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the process is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the process is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the process is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
