<?php

namespace App\Jobs;

use App\Actions\ChunkMarkdown;
use App\Models\ScrapeResult;
use App\Services\OpenAIEmbedding;
use App\Services\VectorDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncToVectorDbJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $scrapeResultId;
    public int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $scrapeResultId, int $userId)
    {
        $this->scrapeResultId = $scrapeResultId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scrapeResult = ScrapeResult::find($this->scrapeResultId);
        
        if (!$scrapeResult) {
            Log::error("ScrapeResult not found: {$this->scrapeResultId}");
            return;
        }

        if (empty($scrapeResult->content)) {
            return;
        }

        try {
            // Initialize services
            $embedding = OpenAIEmbedding::make();
            $vectorDb = VectorDatabase::make();
            
            // Delete existing vectors for this URL to prevent duplicates
            $vectorDb->deleteVectorsByUrl($scrapeResult->source_url, $this->userId);

            // Chunk the markdown content
            $chunks = ChunkMarkdown::make()->handle($scrapeResult->content);
            
            if (empty($chunks)) {
                return;
            }

            // Process chunks in batches
            $this->processChunksInBatches($chunks, $scrapeResult, $embedding, $vectorDb);


            
        } catch (\Exception $e) {
            Log::error("SyncToVectorDbJob failed for ScrapeResult {$this->scrapeResultId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process chunks in batches for better performance.
     */
    protected function processChunksInBatches(array $chunks, ScrapeResult $scrapeResult, OpenAIEmbedding $embedding, VectorDatabase $vectorDb): void
    {
        $batchSize = 10; // Process 10 chunks at a time
        $totalChunks = count($chunks);
        
        for ($i = 0; $i < $totalChunks; $i += $batchSize) {
            $chunkBatch = array_slice($chunks, $i, $batchSize);
            $this->processBatch($chunkBatch, $scrapeResult, $embedding, $vectorDb, $i, $totalChunks);
        }
    }

    /**
     * Process a batch of chunks.
     */
    protected function processBatch(array $chunks, ScrapeResult $scrapeResult, OpenAIEmbedding $embedding, VectorDatabase $vectorDb, int $startIndex, int $totalChunks): void
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
                Log::warning("Failed to generate embedding for chunk {$index} of ScrapeResult {$this->scrapeResultId}");
                continue;
            }

            $chunkIndex = $startIndex + $index;
            $vectorId = $this->generateVectorId($scrapeResult, $chunkIndex);
            
            $metadata = [
                'scrape_result_id' => $scrapeResult->id,
                'type' => 'Website',
                'url' => $scrapeResult->source_url,
                'content' => $this->truncateContent($chunk['content']),
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks,
                'word_count' => $chunk['word_count'] ?? 0,
                'chunk_type' => $chunk['metadata']['type'] ?? 'unknown',
                'created_at' => now()->toISOString(),
            ];

            // Only include author if it's not null (Pinecone doesn't accept null values)
            if ($scrapeResult->author !== null) {
                $metadata['author'] = $scrapeResult->author;
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
                Log::error("Failed to upsert batch starting at index {$startIndex} for ScrapeResult {$this->scrapeResultId}");
                throw new \Exception("Failed to upsert vectors to Pinecone");
            }
            

        }
    }

    /**
     * Generate a unique vector ID.
     */
    protected function generateVectorId(ScrapeResult $scrapeResult, int $chunkIndex): string
    {
        return "scrape_result_{$scrapeResult->id}_chunk_{$chunkIndex}";
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
        Log::error("SyncToVectorDbJob failed for ScrapeResult {$this->scrapeResultId}: " . $exception->getMessage());
    }
} 