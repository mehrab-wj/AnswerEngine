<?php

namespace App\Actions;

use App\Models\PdfDocument;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckPdfStatus
{
    use AsAction;

    public string $commandSignature = 'pdf:status {uuid : PDF document UUID}';
    public string $commandDescription = 'Check the processing status of a PDF document by UUID.';

    /**
     * Check the status of a PDF document.
     *
     * @param string $uuid The PDF document UUID
     * @return array Status information
     */
    public function handle(string $uuid): array
    {
        $pdfDocument = PdfDocument::findByUuid($uuid);
        
        if (!$pdfDocument) {
            return [
                'found' => false,
                'uuid' => $uuid,
                'message' => 'PDF document not found',
            ];
        }

        return [
            'found' => true,
            'uuid' => $pdfDocument->uuid,
            'status' => $pdfDocument->status,
            'filename' => $pdfDocument->original_filename,
            'file_size' => $pdfDocument->file_size,
            'formatted_file_size' => $pdfDocument->formatted_file_size,
            'user_id' => $pdfDocument->user_id,
            'created_at' => $pdfDocument->created_at,
            'updated_at' => $pdfDocument->updated_at,
            'driver_used' => $pdfDocument->driver_used,
            'processing_time' => $pdfDocument->processing_time,
            'formatted_processing_time' => $pdfDocument->formatted_processing_time,
            'text_length' => $pdfDocument->text_length,
            'markdown_length' => $pdfDocument->markdown_length,
            'page_count' => $pdfDocument->page_count,
            'document_title' => $pdfDocument->document_title,
            'error_message' => $pdfDocument->error_message,
            'metadata' => $pdfDocument->metadata,
        ];
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $uuid = (string) $command->argument('uuid');
        
        $command->info("Checking status for PDF document: {$uuid}");
        
        $result = $this->handle($uuid);
        
        if (!$result['found']) {
            $command->error($result['message']);
            return;
        }

        // Display basic information
        $command->info("PDF Document Status");
        $command->table(
            ['Property', 'Value'],
            [
                ['UUID', $result['uuid']],
                ['Status', $this->getStatusWithIcon($result['status'])],
                ['Filename', $result['filename']],
                ['File Size', $result['formatted_file_size']],
                ['User ID', $result['user_id']],
                ['Created At', $result['created_at']->format('Y-m-d H:i:s')],
                ['Updated At', $result['updated_at']->format('Y-m-d H:i:s')],
            ]
        );

        // Display processing information if available
        if ($result['status'] === 'completed') {
            $command->info("\nProcessing Results:");
            $command->table(
                ['Property', 'Value'],
                [
                    ['Driver Used', $result['driver_used'] ?? 'N/A'],
                    ['Processing Time', $result['formatted_processing_time']],
                    ['Text Length', number_format($result['text_length']) . ' characters'],
                    ['Markdown Length', number_format($result['markdown_length']) . ' characters'],
                    ['Page Count', $result['page_count']],
                    ['Document Title', $result['document_title'] ?? 'N/A'],
                ]
            );

            // Display metadata if available
            if (!empty($result['metadata'])) {
                $command->info("\nMetadata:");
                foreach ($result['metadata'] as $key => $value) {
                    if ($value !== null && $value !== '' && $key !== 'raw_details') {
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        $command->line("  {$key}: {$value}");
                    }
                }
            }
        } elseif ($result['status'] === 'failed') {
            $command->error("\nProcessing Failed:");
            $command->error($result['error_message'] ?? 'Unknown error');
        } elseif ($result['status'] === 'processing') {
            $command->info("\nDocument is currently being processed...");
        } elseif ($result['status'] === 'pending') {
            $command->info("\nDocument is queued for processing...");
        }
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
} 