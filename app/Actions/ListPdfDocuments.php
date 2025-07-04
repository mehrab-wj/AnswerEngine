<?php

namespace App\Actions;

use App\Models\PdfDocument;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ListPdfDocuments
{
    use AsAction;

    public string $commandSignature = 'pdf:list {--status= : Filter by status (pending, processing, completed, failed)} {--user= : Filter by user ID} {--limit=20 : Number of documents to display} {--stats : Show statistics}';
    public string $commandDescription = 'List all PDF documents with their processing statuses.';

    /**
     * List PDF documents with optional filtering.
     *
     * @param string|null $status Filter by status
     * @param int|null $userId Filter by user ID
     * @param int $limit Number of documents to display
     * @return array
     */
    public function handle(?string $status = null, ?int $userId = null, int $limit = 20): array
    {
        $query = PdfDocument::query()
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $documents = $query->limit($limit)->get();

        // Get statistics
        $stats = [
            'total_documents' => PdfDocument::count(),
            'pending_documents' => PdfDocument::pending()->count(),
            'processing_documents' => PdfDocument::processing()->count(),
            'completed_documents' => PdfDocument::completed()->count(),
            'failed_documents' => PdfDocument::failed()->count(),
            'total_storage_size' => PdfDocument::sum('file_size'),
            'average_processing_time' => PdfDocument::whereNotNull('processing_time')->avg('processing_time'),
        ];

        return [
            'documents' => $documents,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'user_id' => $userId,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $status = $command->option('status');
        $userId = $command->option('user') ? (int) $command->option('user') : null;
        $limit = (int) $command->option('limit');
        $showStats = $command->option('stats');

        $command->info("Listing PDF documents");
        
        if ($status) {
            $command->info("Filtered by status: {$status}");
        }
        
        if ($userId) {
            $command->info("Filtered by user ID: {$userId}");
        }
        
        $command->info("Limit: {$limit}");

        $result = $this->handle($status, $userId, $limit);

        // Show statistics if requested
        if ($showStats) {
            $command->info("\nStatistics:");
            $command->table(
                ['Metric', 'Value'],
                [
                    ['Total Documents', number_format($result['stats']['total_documents'])],
                    ['Pending', number_format($result['stats']['pending_documents'])],
                    ['Processing', number_format($result['stats']['processing_documents'])],
                    ['Completed', number_format($result['stats']['completed_documents'])],
                    ['Failed', number_format($result['stats']['failed_documents'])],
                    ['Total Storage Size', $this->formatBytes($result['stats']['total_storage_size'])],
                    ['Average Processing Time', $this->formatProcessingTime($result['stats']['average_processing_time'])],
                ]
            );
        }

        // Show documents
        if ($result['documents']->isEmpty()) {
            $command->info("\nNo documents found.");
            return;
        }

        $command->info("\nPDF Documents:");
        
        $tableData = [];
        foreach ($result['documents'] as $document) {
            $tableData[] = [
                substr($document->uuid, 0, 8) . '...',
                $this->getStatusWithIcon($document->status),
                $this->truncate($document->original_filename, 30),
                $document->formatted_file_size,
                $document->user_id,
                $document->formatted_processing_time,
                $document->created_at->format('Y-m-d H:i'),
            ];
        }

        $command->table(
            ['UUID', 'Status', 'Filename', 'Size', 'User', 'Time', 'Created'],
            $tableData
        );

        $command->info("\nShowing {$result['documents']->count()} of {$result['stats']['total_documents']} documents.");
        $command->info("Use 'php artisan pdf:status <uuid>' to view details of a specific document.");
    }

    /**
     * Get status with icon for better visual representation.
     */
    protected function getStatusWithIcon(string $status): string
    {
        return match ($status) {
            'pending' => '⏳ Pending',
            'processing' => '⚙️ Processing',
            'completed' => '✅ Completed',
            'failed' => '❌ Failed',
            default => $status,
        };
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format processing time for display.
     */
    protected function formatProcessingTime(?float $seconds): string
    {
        if (!$seconds) {
            return 'N/A';
        }

        if ($seconds < 1) {
            return number_format($seconds * 1000, 0) . 'ms';
        }
        
        if ($seconds < 60) {
            return number_format($seconds, 1) . 's';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return $minutes . 'm' . number_format($remainingSeconds, 0) . 's';
    }

    /**
     * Truncate text to specified length.
     */
    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
} 