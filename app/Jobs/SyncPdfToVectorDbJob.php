<?php

namespace App\Jobs;

use App\Actions\ChunkMarkdown;
use App\Models\PdfDocument;
use App\Services\OpenAIEmbedding;
use App\Services\VectorDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPdfToVectorDbJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $pdfDocumentId;
    public int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $pdfDocumentId, int $userId)
    {
        $this->pdfDocumentId = $pdfDocumentId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pdfDocument = PdfDocument::find($this->pdfDocumentId);
        
        if (!$pdfDocument) {
            Log::error("PdfDocument not found: {$this->pdfDocumentId}");
            return;
        }

        if (empty($pdfDocument->markdown_text)) {
            Log::warning("No markdown content for PdfDocument {$this->pdfDocumentId}");
            return;
        }

        try {
            // Initialize services
            $embedding = OpenAIEmbedding::make();
            $vectorDb = VectorDatabase::make();
            
            // Delete existing vectors for this PDF to prevent duplicates
            $vectorDb->deleteVectorsByUrl($pdfDocument->uuid, $this->userId);

            // Chunk the markdown content
            $chunks = ChunkMarkdown::make()->handle($pdfDocument->markdown_text);
            
            if (empty($chunks)) {
                Log::warning("No chunks generated for PdfDocument {$this->pdfDocumentId}");
                return;
            }

            // Process chunks in batches
            $this->processChunksInBatches($chunks, $pdfDocument, $embedding, $vectorDb);

        } catch (\Exception $e) {
            Log::error("SyncPdfToVectorDbJob failed for PdfDocument {$this->pdfDocumentId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process chunks in batches for better performance.
     */
    protected function processChunksInBatches(array $chunks, PdfDocument $pdfDocument, OpenAIEmbedding $embedding, VectorDatabase $vectorDb): void
    {
        $batchSize = 10; // Process 10 chunks at a time
        $totalChunks = count($chunks);
        
        for ($i = 0; $i < $totalChunks; $i += $batchSize) {
            $chunkBatch = array_slice($chunks, $i, $batchSize);
            $this->processBatch($chunkBatch, $pdfDocument, $embedding, $vectorDb, $i, $totalChunks);
        }
    }

    /**
     * Process a batch of chunks.
     */
    protected function processBatch(array $chunks, PdfDocument $pdfDocument, OpenAIEmbedding $embedding, VectorDatabase $vectorDb, int $startIndex, int $totalChunks): void
    {
        // Extract content from chunks for batch embedding
        $contents = [];
        foreach ($chunks as $chunk) {
            $contents[] = $chunk['content'];
        }

        // Generate embeddings for all chunks in batch
        $embeddings = $embedding->batchEmbed($contents);
        
        // Prepare vectors for Pinecone
        $vectors = [];
        foreach ($chunks as $index => $chunk) {
            $embeddingVector = $embeddings[$index] ?? null;
            
            if (!$embeddingVector) {
                Log::warning("Failed to generate embedding for chunk {$index} of PdfDocument {$this->pdfDocumentId}");
                continue;
            }

            $chunkIndex = $startIndex + $index;
            $vectorId = $this->generateVectorId($pdfDocument, $chunkIndex);
            
            $metadata = [
                'pdf_document_id' => $pdfDocument->id,
                'type' => 'PDF',
                'url' => $pdfDocument->uuid, // Use UUID as unique identifier
                'filename' => $pdfDocument->original_filename,
                'content' => $this->truncateContent($chunk['content']),
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks,
                'word_count' => $chunk['word_count'] ?? 0,
                'chunk_type' => $chunk['metadata']['type'] ?? 'unknown',
                'page_count' => $pdfDocument->page_count,
                'driver_used' => $pdfDocument->driver_used ?? 'unknown',
                'created_at' => now()->toISOString(),
            ];

            // Only include document title if it's not null (Pinecone doesn't accept null values)
            if ($pdfDocument->document_title !== null) {
                $metadata['document_title'] = $pdfDocument->document_title;
            }

            $vectors[] = [
                'id' => $vectorId,
                'values' => $embeddingVector,
                'metadata' => $metadata,
            ];
        }

        // Upload vectors to Pinecone
        if (!empty($vectors)) {
            $success = $vectorDb->upsertVectors($vectors, $this->userId);
            
            if (!$success) {
                Log::error("Failed to upsert batch starting at index {$startIndex} for PdfDocument {$this->pdfDocumentId}");
                throw new \Exception("Failed to upsert vectors to Pinecone");
            }

            Log::info("Successfully upserted batch for PdfDocument {$this->pdfDocumentId}", [
                'batch_start' => $startIndex,
                'batch_size' => count($vectors),
                'total_chunks' => $totalChunks
            ]);
        }
    }

    /**
     * Generate a unique vector ID.
     */
    protected function generateVectorId(PdfDocument $pdfDocument, int $chunkIndex): string
    {
        return "pdf_document_{$pdfDocument->id}_chunk_{$chunkIndex}";
    }

    /**
     * Truncate content for metadata to stay within Pinecone limits.
     */
    protected function truncateContent(string $content, int $maxLength = 1000): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        return substr($content, 0, $maxLength - 3) . '...';
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncPdfToVectorDbJob failed for PdfDocument {$this->pdfDocumentId}: " . $exception->getMessage());
    }
} 