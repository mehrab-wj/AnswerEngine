<?php

namespace App\Services;

use Probots\Pinecone\Client as Pinecone;
use Illuminate\Support\Facades\Log;

class VectorDatabase
{
    protected Pinecone $pinecone;

    public function __construct()
    {
        $this->pinecone = new Pinecone(
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX_HOST')
        );
    }

    /**
     * Upsert vectors with metadata in user namespace.
     */
    public function upsertVectors(array $vectors, int $userId): bool
    {
        $namespace = $this->getUserNamespace($userId);
        
        try {
            // Format vectors for Pinecone
            $formattedVectors = [];
            foreach ($vectors as $vector) {
                $formattedVectors[] = [
                    'id' => $vector['id'],
                    'values' => $vector['values'],
                    'metadata' => array_merge($vector['metadata'], [
                        'user_id' => $userId,
                        'namespace' => $namespace,
                    ])
                ];
            }

            $response = $this->pinecone->data()->vectors()->upsert(
                vectors: $formattedVectors,
                namespace: $namespace
            );

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Pinecone upsert failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete vectors by URL in user namespace.
     */
    public function deleteVectorsByUrl(string $url, int $userId): bool
    {
        $namespace = $this->getUserNamespace($userId);
        
        try {
            // Query vectors by URL to get IDs
            $queryResponse = $this->pinecone->data()->vectors()->query(
                vector: array_fill(0, 1536, 0), // Dummy vector for metadata filtering
                topK: 10000, // Large number to get all matches
                filter: [
                    'url' => $url,
                    'user_id' => $userId
                ],
                namespace: $namespace,
                includeMetadata: true
            );

            if (!$queryResponse->successful()) {
                Log::warning("Failed to query vectors for deletion: {$url}");
                return false;
            }

            $data = $queryResponse->json();
            $vectorIds = [];
            
            if (isset($data['matches'])) {
                foreach ($data['matches'] as $match) {
                    $vectorIds[] = $match['id'];
                }
            }

            if (empty($vectorIds)) {
                return true;
            }

            // Delete vectors by IDs
            $deleteResponse = $this->pinecone->data()->vectors()->delete(
                ids: $vectorIds,
                namespace: $namespace
            );

            $success = $deleteResponse->successful();
            
            if (!$success) {
                Log::error("Failed to delete vectors for URL: {$url}");
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Vector deletion failed for URL {$url}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Query vectors in user namespace.
     */
    public function queryVectors(array $vector, int $topK, int $userId, array $filter = []): array
    {
        $namespace = $this->getUserNamespace($userId);
        
        try {
            $response = $this->pinecone->data()->vectors()->query(
                vector: $vector,
                topK: $topK,
                filter: array_merge($filter, ['user_id' => $userId]),
                namespace: $namespace,
                includeMetadata: true
            );

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error("Vector query failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get index stats for user namespace.
     */
    public function getNamespaceStats(int $userId): array
    {
        $namespace = $this->getUserNamespace($userId);
        
        try {
            $response = $this->pinecone->data()->vectors()->stats();

            if ($response->successful()) {
                $data = $response->json();
                return $data['namespaces'][$namespace] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error("Failed to get namespace stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete all vectors for a user.
     */
    public function deleteUserVectors(int $userId): bool
    {
        $namespace = $this->getUserNamespace($userId);
        
        try {
            $response = $this->pinecone->data()->vectors()->delete(
                deleteAll: true,
                namespace: $namespace
            );

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Failed to delete all vectors for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate user namespace.
     */
    protected function getUserNamespace(int $userId): string
    {
        return "user_{$userId}";
    }

    /**
     * Create a static instance.
     */
    public static function make(): self
    {
        return new self();
    }
} 