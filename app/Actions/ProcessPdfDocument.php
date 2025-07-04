<?php

namespace App\Actions;

use App\Jobs\ProcessPdfDocumentJob;
use App\Models\PdfDocument;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPdfDocument
{
    use AsAction;

    public string $commandSignature = 'pdf:process {file} {--user=1 : User ID} {--driver= : Driver to use} {--no-markdown : Skip markdown conversion} {--sync : Process synchronously}';
    public string $commandDescription = 'Process a PDF document and store the extracted content in the database.';

    /**
     * Process a PDF document from file path.
     *
     * @param string $filePath The path to the PDF file
     * @param int $userId The user ID
     * @param string|null $driver The driver to use
     * @param bool $convertToMarkdown Whether to convert to markdown
     * @param bool $processSync Whether to process synchronously
     * @return PdfDocument
     * @throws PdfExtractionException
     */
    public function handle(
        string $filePath,
        int $userId,
        ?string $driver = null,
        bool $convertToMarkdown = true,
        bool $processSync = false
    ): PdfDocument {
        // Validate file exists
        if (!file_exists($filePath)) {
            throw PdfExtractionException::fileNotFound($filePath);
        }

        // Validate file is PDF
        $mimeType = mime_content_type($filePath);
        if ($mimeType !== 'application/pdf') {
            throw PdfExtractionException::invalidFile($filePath);
        }

        // Get file info
        $originalFilename = basename($filePath);
        $fileSize = filesize($filePath);

        // Generate storage path
        $storagePath = 'pdf-documents/' . date('Y/m/d') . '/' . Str::uuid() . '.pdf';

        // Store file
        $fileContent = file_get_contents($filePath);
        Storage::put($storagePath, $fileContent);

        // Create PDF document record
        $pdfDocument = PdfDocument::create([
            'original_filename' => $originalFilename,
            'storage_path' => $storagePath,
            'file_size' => $fileSize,
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        // Process the document
        if ($processSync) {
            // Process synchronously
            $job = new ProcessPdfDocumentJob($pdfDocument, $driver, $convertToMarkdown);
            $job->handle(new ExtractPdfText());
        } else {
            // Dispatch job for background processing
            ProcessPdfDocumentJob::dispatch($pdfDocument, $driver, $convertToMarkdown);
        }

        return $pdfDocument;
    }

    /**
     * Process a PDF document from uploaded file.
     *
     * @param UploadedFile $file The uploaded file
     * @param int $userId The user ID
     * @param string|null $driver The driver to use
     * @param bool $convertToMarkdown Whether to convert to markdown
     * @param bool $processSync Whether to process synchronously
     * @return PdfDocument
     * @throws PdfExtractionException
     */
    public function fromUploadedFile(
        UploadedFile $file,
        int $userId,
        ?string $driver = null,
        bool $convertToMarkdown = true,
        bool $processSync = false
    ): PdfDocument {
        // Validate file
        if (!$file->isValid()) {
            throw new PdfExtractionException("Invalid uploaded file");
        }

        if ($file->getMimeType() !== 'application/pdf') {
            throw new PdfExtractionException("File must be a PDF");
        }

        // Get file info
        $originalFilename = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        // Generate storage path
        $storagePath = 'pdf-documents/' . date('Y/m/d') . '/' . Str::uuid() . '.pdf';

        // Store file
        $file->storeAs('', $storagePath);

        // Create PDF document record
        $pdfDocument = PdfDocument::create([
            'original_filename' => $originalFilename,
            'storage_path' => $storagePath,
            'file_size' => $fileSize,
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        // Process the document
        if ($processSync) {
            // Process synchronously
            $job = new ProcessPdfDocumentJob($pdfDocument, $driver, $convertToMarkdown);
            $job->handle(new ExtractPdfText());
        } else {
            // Dispatch job for background processing
            ProcessPdfDocumentJob::dispatch($pdfDocument, $driver, $convertToMarkdown);
        }

        return $pdfDocument;
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $filePath = (string) $command->argument('file');
        $userId = (int) $command->option('user');
        $driver = $command->option('driver');
        $convertToMarkdown = !$command->option('no-markdown');
        $processSync = $command->option('sync');

        $command->info("Processing PDF document: {$filePath}");
        $command->info("User ID: {$userId}");
        
        if ($driver) {
            $command->info("Driver: {$driver}");
        }
        
        $command->info("Convert to Markdown: " . ($convertToMarkdown ? 'Yes' : 'No'));
        $command->info("Process Mode: " . ($processSync ? 'Synchronous' : 'Background'));

        try {
            $pdfDocument = $this->handle($filePath, $userId, $driver, $convertToMarkdown, $processSync);

            $command->info("PDF document created successfully!");
            $command->table(
                ['Property', 'Value'],
                [
                    ['UUID', $pdfDocument->uuid],
                    ['Original Filename', $pdfDocument->original_filename],
                    ['File Size', $pdfDocument->formatted_file_size],
                    ['Status', $pdfDocument->status],
                    ['Storage Path', $pdfDocument->storage_path],
                    ['Created At', $pdfDocument->created_at->format('Y-m-d H:i:s')],
                ]
            );

            if ($processSync) {
                // Refresh the model to get updated data
                $pdfDocument->refresh();
                
                $command->info("\nProcessing completed!");
                $command->table(
                    ['Property', 'Value'],
                    [
                        ['Status', $pdfDocument->status],
                        ['Driver Used', $pdfDocument->driver_used ?? 'N/A'],
                        ['Processing Time', $pdfDocument->formatted_processing_time],
                        ['Text Length', number_format($pdfDocument->text_length) . ' characters'],
                        ['Pages', $pdfDocument->page_count],
                    ]
                );

                if ($pdfDocument->hasFailed()) {
                    $command->error("Processing failed: " . $pdfDocument->error_message);
                }
            } else {
                $command->info("\nJob dispatched for background processing.");
                $command->info("Use 'php artisan pdf:status {$pdfDocument->uuid}' to check progress.");
            }

        } catch (PdfExtractionException $e) {
            $command->error("Processing failed: " . $e->getMessage());
            
            if ($e->getDriver()) {
                $command->error("Driver: " . $e->getDriver());
            }
        } catch (\Exception $e) {
            $command->error("Unexpected error: " . $e->getMessage());
        }
    }

    /**
     * Get processing statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total_documents' => PdfDocument::count(),
            'pending_documents' => PdfDocument::pending()->count(),
            'processing_documents' => PdfDocument::processing()->count(),
            'completed_documents' => PdfDocument::completed()->count(),
            'failed_documents' => PdfDocument::failed()->count(),
            'total_storage_size' => PdfDocument::sum('file_size'),
            'average_processing_time' => PdfDocument::whereNotNull('processing_time')->avg('processing_time'),
            'success_rate' => PdfDocument::count() > 0 
                ? round((PdfDocument::completed()->count() / PdfDocument::count()) * 100, 2) 
                : 0,
        ];
    }
} 