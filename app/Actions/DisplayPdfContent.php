<?php

namespace App\Actions;

use App\Models\PdfDocument;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DisplayPdfContent
{
    use AsAction;

    public string $commandSignature = 'pdf:content {uuid : PDF document UUID} {--format=text : Output format (text, markdown)} {--output= : Output file path} {--limit=1000 : Character limit for console output}';
    public string $commandDescription = 'Display the extracted content of a PDF document by UUID.';

    /**
     * Display the content of a PDF document.
     *
     * @param string $uuid The PDF document UUID
     * @param string $format Output format (text or markdown)
     * @param int $limit Character limit for console output
     * @return array Content information
     */
    public function handle(string $uuid, string $format = 'text', int $limit = 1000): array
    {
        $pdfDocument = PdfDocument::findByUuid($uuid);
        
        if (!$pdfDocument) {
            return [
                'found' => false,
                'uuid' => $uuid,
                'message' => 'PDF document not found',
            ];
        }

        if (!$pdfDocument->isCompleted()) {
            return [
                'found' => true,
                'uuid' => $uuid,
                'status' => $pdfDocument->status,
                'message' => "PDF document is not completed yet (status: {$pdfDocument->status})",
            ];
        }

        $content = $format === 'markdown' ? $pdfDocument->markdown_text : $pdfDocument->extracted_text;
        
        if (!$content) {
            return [
                'found' => true,
                'uuid' => $uuid,
                'status' => $pdfDocument->status,
                'message' => "No {$format} content available for this document",
            ];
        }

        return [
            'found' => true,
            'uuid' => $uuid,
            'status' => $pdfDocument->status,
            'format' => $format,
            'content' => $content,
            'content_length' => strlen($content),
            'truncated' => strlen($content) > $limit,
            'preview' => $limit > 0 ? substr($content, 0, $limit) : $content,
            'filename' => $pdfDocument->original_filename,
            'document_title' => $pdfDocument->document_title,
            'processing_info' => [
                'driver_used' => $pdfDocument->driver_used,
                'processing_time' => $pdfDocument->formatted_processing_time,
                'page_count' => $pdfDocument->page_count,
            ],
        ];
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $uuid = (string) $command->argument('uuid');
        $format = (string) $command->option('format');
        $outputPath = $command->option('output');
        $limit = (int) $command->option('limit');

        $command->info("Displaying content for PDF document: {$uuid}");
        $command->info("Format: {$format}");
        
        $result = $this->handle($uuid, $format, $outputPath ? 0 : $limit);
        
        if (!$result['found']) {
            $command->error($result['message']);
            return;
        }

        if (!isset($result['content'])) {
            $command->error($result['message']);
            return;
        }

        // Display document information
        $command->info("Document Information:");
        $command->table(
            ['Property', 'Value'],
            [
                ['UUID', $result['uuid']],
                ['Status', $result['status']],
                ['Filename', $result['filename']],
                ['Document Title', $result['document_title'] ?? 'N/A'],
                ['Content Format', $result['format']],
                ['Content Length', number_format($result['content_length']) . ' characters'],
                ['Driver Used', $result['processing_info']['driver_used'] ?? 'N/A'],
                ['Processing Time', $result['processing_info']['processing_time']],
                ['Page Count', $result['processing_info']['page_count']],
            ]
        );

        // Handle output to file
        if ($outputPath) {
            file_put_contents($outputPath, $result['content']);
            $command->info("\nContent saved to: {$outputPath}");
            $command->info("File size: " . $this->formatBytes(filesize($outputPath)));
            return;
        }

        // Display content in console
        $command->info("\nExtracted Content:");
        $command->line(str_repeat('=', 80));
        
        if ($result['truncated']) {
            $command->line($result['preview']);
            $command->line(str_repeat('=', 80));
            $command->info("Content truncated to {$limit} characters.");
            $command->info("Use --limit=0 to see full content or --output=file.txt to save to file.");
        } else {
            $command->line($result['content']);
            $command->line(str_repeat('=', 80));
        }
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
} 