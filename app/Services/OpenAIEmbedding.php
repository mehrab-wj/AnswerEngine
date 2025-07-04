<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;

class OpenAIEmbedding
{
    protected Client $client;
    protected string $model = 'text-embedding-3-small';

    public function __construct()
    {
        $this->client = OpenAI::client(env('OPENAI_API_KEY'));
    }

    /**
     * Generate embeddings for a single text.
     */
    public function embed(string $text): ?array
    {
        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $text,
                'encoding_format' => 'float',
            ]);

            return $response->embeddings[0]->embedding;
        } catch (\Exception $e) {
            Log::error("OpenAI embedding failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate embeddings for multiple texts in batch.
     */
    public function batchEmbed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $texts,
                'encoding_format' => 'float',
            ]);

            $embeddings = [];
            foreach ($response->embeddings as $embedding) {
                $embeddings[] = $embedding->embedding;
            }

            return $embeddings;
        } catch (\Exception $e) {
            Log::error("OpenAI batch embedding failed: " . $e->getMessage());
            
            // Fallback to individual embeddings
            $embeddings = [];
            foreach ($texts as $text) {
                $embedding = $this->embed($text);
                $embeddings[] = $embedding;
            }

            return $embeddings;
        }
    }

    /**
     * Get the embedding model dimensions.
     */
    public function getDimensions(): int
    {
        return 1536; // text-embedding-3-small dimensions
    }

    /**
     * Get the model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Create a static instance.
     */
    public static function make(): self
    {
        return new self();
    }
} 