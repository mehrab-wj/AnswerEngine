<?php

namespace App\Actions;

use App\Services\PdfTextExtractor\PdfTextExtractor;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class ExtractPdfText
{
    use AsAction;

    public string $commandSignature = 'pdf:extract {file} {--driver= : Driver to use for extraction} {--markdown : Convert extracted text to markdown} {--output= : Output file path}';
    public string $commandDescription = 'Extract text from a PDF file using configurable drivers.';

    /**
     * Extract text from a PDF file.
     *
     * @param string $filePath The path to the PDF file
     * @param string|null $driver The driver to use (null for default)
     * @param bool $convertToMarkdown Whether to convert text to markdown
     * @return array Result containing text, metadata, and processing info
     * @throws PdfExtractionException
     */
    public function handle(string $filePath, ?string $driver = null, bool $convertToMarkdown = true): array
    {
        // Create the PDF text extractor
        $extractor = new PdfTextExtractor($driver);

        // Extract text
        $text = $extractor->extract($filePath);

        // Extract metadata
        $metadata = [];
        try {
            $metadata = $extractor->extractMetadata($filePath);
        } catch (PdfExtractionException $e) {
            // Metadata extraction failed, but we can continue with text
        }

        // Convert to markdown if requested
        if ($convertToMarkdown) {
            $text = ConvertTextToMarkdown::run($text);
        }

        return [
            'text' => $text,
            'metadata' => $metadata,
            'driver_used' => $extractor->getCurrentDriver(),
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'text_length' => strlen($text),
            'converted_to_markdown' => $convertToMarkdown,
        ];
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $filePath = (string) $command->argument('file');
        $driver = $command->option('driver');
        $convertToMarkdown = $command->option('markdown');
        $outputPath = $command->option('output');

        // Validate file exists
        if (!file_exists($filePath)) {
            $command->error("File not found: {$filePath}");
            return;
        }

        $command->info("Extracting text from PDF: {$filePath}");
        
        if ($driver) {
            $command->info("Using driver: {$driver}");
        }

        if ($convertToMarkdown) {
            $command->info("Converting to markdown...");
        }

        try {
            $result = $this->handle($filePath, $driver, $convertToMarkdown);

            // Display results
            $command->info("Extraction completed successfully!");
            $command->table(
                ['Property', 'Value'],
                [
                    ['Driver Used', $result['driver_used']],
                    ['File Size', $this->formatBytes($result['file_size'])],
                    ['Text Length', number_format($result['text_length']) . ' characters'],
                    ['Processing Time', number_format($result['processing_time'] * 1000, 2) . ' ms'],
                    ['Converted to Markdown', $result['converted_to_markdown'] ? 'Yes' : 'No'],
                ]
            );

            // Display metadata if available
            if (!empty($result['metadata'])) {
                $command->info("\nMetadata:");
                foreach ($result['metadata'] as $key => $value) {
                    if ($value !== null && $value !== '') {
                        // Skip arrays or convert them to readable format
                        if (is_array($value)) {
                            if ($key === 'raw_details') {
                                // Skip raw details as they're too verbose
                                continue;
                            }
                            $value = implode(', ', $value);
                        }
                        $command->line("  {$key}: {$value}");
                    }
                }
            }

            // Output text
            if ($outputPath) {
                file_put_contents($outputPath, $result['text']);
                $command->info("\nText saved to: {$outputPath}");
            } else {
                $command->info("\nExtracted text:");
                $command->line($result['text']);
            }

        } catch (PdfExtractionException $e) {
            $command->error("Extraction failed: " . $e->getMessage());
            
            if ($e->getDriver()) {
                $command->error("Driver: " . $e->getDriver());
            }
            
            if ($e->getFilePath()) {
                $command->error("File: " . $e->getFilePath());
            }
        }
    }

    /**
     * Extract text from a PDF file stored in Laravel storage.
     *
     * @param string $storagePath The storage path (e.g., 'pdfs/document.pdf')
     * @param string $disk The storage disk (default: 'local')
     * @param string|null $driver The driver to use
     * @param bool $convertToMarkdown Whether to convert to markdown
     * @return array
     * @throws PdfExtractionException
     */
    public function fromStorage(
        string $storagePath,
        string $disk = 'local',
        ?string $driver = null,
        bool $convertToMarkdown = false
    ): array {
        $storage = Storage::disk($disk);
        
        if (!$storage->exists($storagePath)) {
            throw PdfExtractionException::fileNotFound($storagePath);
        }

        // Get the full path
        $fullPath = $storage->path($storagePath);

        return $this->handle($fullPath, $driver, $convertToMarkdown);
    }

    /**
     * Extract text from uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string|null $driver The driver to use
     * @param bool $convertToMarkdown Whether to convert to markdown
     * @return array
     * @throws PdfExtractionException
     */
    public function fromUploadedFile(
        \Illuminate\Http\UploadedFile $file,
        ?string $driver = null,
        bool $convertToMarkdown = false
    ): array {
        // Validate file
        if (!$file->isValid()) {
            throw new PdfExtractionException("Invalid uploaded file");
        }

        if ($file->getMimeType() !== 'application/pdf') {
            throw new PdfExtractionException("File must be a PDF");
        }

        return $this->handle($file->getPathname(), $driver, $convertToMarkdown);
    }

    /**
     * Get available drivers.
     *
     * @return array
     */
    public function getAvailableDrivers(): array
    {
        $extractor = new PdfTextExtractor();
        return $extractor->getAvailableDrivers();
    }

    /**
     * Test driver availability.
     *
     * @param string $driver
     * @return bool
     */
    public function testDriver(string $driver): bool
    {
        try {
            $extractor = new PdfTextExtractor($driver);
            return $extractor->hasDriver($driver);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @return string
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