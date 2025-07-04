<?php

namespace App\Jobs;

use App\Actions\ExtractPdfText;
use App\Models\PdfDocument;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPdfDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The PDF document to process.
     *
     * @var PdfDocument
     */
    protected PdfDocument $pdfDocument;

    /**
     * The driver to use for processing.
     *
     * @var string|null
     */
    protected ?string $driver;

    /**
     * Whether to convert text to markdown.
     *
     * @var bool
     */
    protected bool $convertToMarkdown;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(PdfDocument $pdfDocument, ?string $driver = null, bool $convertToMarkdown = true)
    {
        $this->pdfDocument = $pdfDocument;
        $this->driver = $driver;
        $this->convertToMarkdown = $convertToMarkdown;
    }

    /**
     * Execute the job.
     */
    public function handle(ExtractPdfText $extractPdfText): void
    {
        $startTime = microtime(true);
        
        try {
            // Mark as processing
            $this->pdfDocument->markAsProcessing();

            Log::info('Started processing PDF document', [
                'uuid' => $this->pdfDocument->uuid,
                'filename' => $this->pdfDocument->original_filename,
                'driver' => $this->driver,
                'convert_to_markdown' => $this->convertToMarkdown,
            ]);

            // Extract text from PDF
            $result = $extractPdfText->handle(
                $this->pdfDocument->getFilePath(),
                $this->driver,
                $this->convertToMarkdown
            );

            // Calculate processing time
            $processingTime = microtime(true) - $startTime;

            // Prepare data for storing
            $data = [
                'extracted_text' => $result['text'],
                'metadata' => $result['metadata'],
                'driver_used' => $result['driver_used'],
                'processing_time' => $processingTime,
            ];

            // Add markdown text if converted
            if ($this->convertToMarkdown && isset($result['text'])) {
                $data['markdown_text'] = $result['text']; // The text is already converted to markdown
            }

            // Mark as completed with extracted data
            $this->pdfDocument->markAsCompleted($data);

            Log::info('Successfully processed PDF document', [
                'uuid' => $this->pdfDocument->uuid,
                'filename' => $this->pdfDocument->original_filename,
                'driver_used' => $result['driver_used'],
                'processing_time' => $processingTime,
                'text_length' => strlen($result['text']),
                'pages' => $result['metadata']['pages'] ?? 0,
            ]);

        } catch (PdfExtractionException $e) {
            $this->handleFailure($e, $startTime);
        } catch (\Exception $e) {
            $this->handleFailure($e, $startTime);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PDF document processing job failed permanently', [
            'uuid' => $this->pdfDocument->uuid,
            'filename' => $this->pdfDocument->original_filename,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark as failed
        $this->pdfDocument->markAsFailed($exception->getMessage());
    }

    /**
     * Handle processing failure.
     */
    protected function handleFailure(\Throwable $exception, float $startTime): void
    {
        $processingTime = microtime(true) - $startTime;

        Log::error('PDF document processing failed', [
            'uuid' => $this->pdfDocument->uuid,
            'filename' => $this->pdfDocument->original_filename,
            'error' => $exception->getMessage(),
            'processing_time' => $processingTime,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
        ]);

        // If this is the last attempt, mark as failed
        if ($this->attempts() >= $this->tries) {
            $this->pdfDocument->markAsFailed($exception->getMessage());
        } else {
            // Reset status to pending for retry
            $this->pdfDocument->update(['status' => 'pending']);
        }

        // Re-throw to trigger retry mechanism
        throw $exception;
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'pdf-processing',
            'document:' . $this->pdfDocument->uuid,
            'user:' . $this->pdfDocument->user_id,
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30s, 1min, 2min
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
