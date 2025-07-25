<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\VectorDatabase;
use Illuminate\Support\Facades\Log;

class PdfDocument extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'original_filename',
        'storage_path',
        'file_size',
        'status',
        'vector_sync_status',
        'extracted_text',
        'markdown_text',
        'metadata',
        'driver_used',
        'processing_time',
        'error_message',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'processing_time' => 'decimal:2',
        'file_size' => 'integer',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::deleted(function ($model) {
            // Clean up the file when the model is deleted
            if ($model->storage_path && Storage::exists($model->storage_path)) {
                Storage::delete($model->storage_path);
            }
            
            // Delete vectors when PdfDocument is deleted
            if ($model->uuid && $model->user_id) {
                try {
                    $vectorDb = VectorDatabase::make();
                    $vectorDb->deleteVectorsByUrl($model->uuid, $model->user_id);
                } catch (\Exception $e) {
                    Log::error("Failed to delete vectors for PdfDocument {$model->id}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Get the user that owns the PDF document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include pending documents.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include processing documents.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope a query to only include completed documents.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed documents.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Check if the document is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the document is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the document is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the document has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark the document as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark the document as completed.
     */
    public function markAsCompleted(array $data = []): void
    {
        $this->update(array_merge(['status' => 'completed'], $data));
    }

    /**
     * Mark the document as failed.
     */
    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get the file path for the stored PDF.
     */
    public function getFilePath(): string
    {
        return Storage::path($this->storage_path);
    }

    /**
     * Get the file size in human readable format.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get the processing time in human readable format.
     */
    public function getFormattedProcessingTimeAttribute(): string
    {
        if (!$this->processing_time) {
            return 'N/A';
        }

        $seconds = $this->processing_time;
        
        if ($seconds < 1) {
            return number_format($seconds * 1000, 0) . ' ms';
        }
        
        if ($seconds < 60) {
            return number_format($seconds, 2) . ' seconds';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return $minutes . 'm ' . number_format($remainingSeconds, 0) . 's';
    }

    /**
     * Get the text length.
     */
    public function getTextLengthAttribute(): int
    {
        return strlen($this->extracted_text ?? '');
    }

    /**
     * Get the markdown length.
     */
    public function getMarkdownLengthAttribute(): int
    {
        return strlen($this->markdown_text ?? '');
    }

    /**
     * Get the page count from metadata.
     */
    public function getPageCountAttribute(): int
    {
        return $this->metadata['pages'] ?? 0;
    }

    /**
     * Get the document title from metadata.
     */
    public function getDocumentTitleAttribute(): ?string
    {
        return $this->metadata['title'] ?? $this->original_filename;
    }

    /**
     * Find a document by UUID.
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Find a document by UUID or fail.
     */
    public static function findByUuidOrFail(string $uuid): self
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Get the content type (always 'pdf' for PDF documents)
     */
    public function getContentTypeAttribute(): string
    {
        return 'pdf';
    }

    /**
     * Convert to expected item format for consistency with ScrapeResult
     */
    public function toExpectedItemFormat(): array
    {
        return [
            'title' => $this->document_title ?: $this->original_filename,
            'content' => $this->markdown_text ?: $this->extracted_text,
            'content_type' => $this->content_type,
            'source_url' => '', // PDFs don't have source URLs
            'filename' => $this->original_filename,
            'author' => '', // PDFs don't have authors in our current implementation
            'user_id' => (string) $this->user_id,
        ];
    }

    /**
     * Mark vector sync as processing.
     */
    public function markVectorSyncAsProcessing(): void
    {
        $this->update(['vector_sync_status' => 'processing']);
    }

    /**
     * Mark vector sync as completed.
     */
    public function markVectorSyncAsCompleted(): void
    {
        $this->update(['vector_sync_status' => 'completed']);
    }

    /**
     * Mark vector sync as failed.
     */
    public function markVectorSyncAsFailed(string $error): void
    {
        $this->update([
            'vector_sync_status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Check if vector sync is pending.
     */
    public function isVectorSyncPending(): bool
    {
        return $this->vector_sync_status === 'pending';
    }

    /**
     * Check if vector sync is processing.
     */
    public function isVectorSyncProcessing(): bool
    {
        return $this->vector_sync_status === 'processing';
    }

    /**
     * Check if vector sync is completed.
     */
    public function isVectorSyncCompleted(): bool
    {
        return $this->vector_sync_status === 'completed';
    }

    /**
     * Check if vector sync has failed.
     */
    public function hasVectorSyncFailed(): bool
    {
        return $this->vector_sync_status === 'failed';
    }
} 