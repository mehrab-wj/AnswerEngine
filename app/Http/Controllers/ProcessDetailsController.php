<?php

namespace App\Http\Controllers;

use App\Models\ScrapeProcess;
use App\Models\PdfDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProcessDetailsController extends Controller
{
    /**
     * Show website scraping process details
     */
    public function showWebsiteProcess(string $uuid)
    {
        $userId = Auth::id();
        
        $scrapeProcess = ScrapeProcess::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->with(['scrapeResults' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->first();

        if (!$scrapeProcess) {
            abort(404, 'Scrape process not found or you do not have permission to view it.');
        }

        // Format the data for the frontend
        $processData = [
            'id' => $scrapeProcess->id,
            'uuid' => $scrapeProcess->uuid,
            'url' => $scrapeProcess->url,
            'status' => $scrapeProcess->status,
            'error_message' => $scrapeProcess->error_message,
            'created_at' => $scrapeProcess->created_at->format('M d, Y H:i:s'),
            'updated_at' => $scrapeProcess->updated_at->format('M d, Y H:i:s'),
            'processing_time' => $scrapeProcess->status === 'completed' ? 
                $scrapeProcess->created_at->diffInSeconds($scrapeProcess->updated_at) : null,
        ];

        // Format scrape results
        $scrapeResults = $scrapeProcess->scrapeResults->map(function($result) {
            return [
                'id' => $result->id,
                'title' => $result->title,
                'source_url' => $result->source_url,
                'author' => $result->author,
                'content' => $result->content,
                'internal_links' => $result->internal_links ?? [],
                'external_links' => $result->external_links ?? [],
                'created_at' => $result->created_at->format('M d, Y H:i:s'),
            ];
        });

        // Calculate statistics
        $stats = [
            'total_links' => $scrapeResults->count(),
            'completed_links' => $scrapeResults->count(), // All results are completed
            'failed_links' => 0, // Failed links wouldn't create results
        ];

        return Inertia::render('process/website-details', [
            'process' => $processData,
            'scrapeResults' => $scrapeResults->values()->toArray(),
            'stats' => $stats,
        ]);
    }

    /**
     * Show PDF document processing details
     */
    public function showPdfProcess(string $uuid)
    {
        $userId = Auth::id();
        
        $pdfDocument = PdfDocument::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->first();

        if (!$pdfDocument) {
            abort(404, 'PDF document not found or you do not have permission to view it.');
        }

        // Format the data for the frontend
        $processData = [
            'id' => $pdfDocument->id,
            'uuid' => $pdfDocument->uuid,
            'original_filename' => $pdfDocument->original_filename,
            'file_size' => $pdfDocument->file_size,
            'formatted_file_size' => $pdfDocument->formatted_file_size,
            'status' => $pdfDocument->status,
            'vector_sync_status' => $pdfDocument->vector_sync_status,
            'extracted_text' => $pdfDocument->extracted_text,
            'markdown_text' => $pdfDocument->markdown_text,
            'metadata' => $pdfDocument->metadata,
            'driver_used' => $pdfDocument->driver_used,
            'processing_time' => $pdfDocument->processing_time,
            'formatted_processing_time' => $pdfDocument->formatted_processing_time,
            'error_message' => $pdfDocument->error_message,
            'created_at' => $pdfDocument->created_at->format('M d, Y H:i:s'),
            'updated_at' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
        ];

        // Calculate statistics
        $stats = [
            'page_count' => $pdfDocument->page_count,
            'text_length' => $pdfDocument->text_length,
            'markdown_length' => $pdfDocument->markdown_length,
            'document_title' => $pdfDocument->document_title,
        ];

        // Processing timeline
        $timeline = $this->buildProcessingTimeline($pdfDocument);

        return Inertia::render('process/pdf-details', [
            'process' => $processData,
            'stats' => $stats,
            'timeline' => $timeline,
        ]);
    }

    /**
     * Build processing timeline for PDF document
     */
    private function buildProcessingTimeline(PdfDocument $pdfDocument): array
    {
        $timeline = [];
        
        // Upload step
        $timeline[] = [
            'step' => 'Upload',
            'status' => 'completed',
            'timestamp' => $pdfDocument->created_at->format('M d, Y H:i:s'),
            'description' => 'PDF file uploaded successfully',
        ];

        // Text extraction step
        if ($pdfDocument->extracted_text) {
            $timeline[] = [
                'step' => 'Text Extraction',
                'status' => 'completed',
                'timestamp' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
                'description' => 'Text extracted using ' . ($pdfDocument->driver_used ?? 'Unknown driver'),
            ];
        } else if ($pdfDocument->status === 'processing') {
            $timeline[] = [
                'step' => 'Text Extraction',
                'status' => 'processing',
                'timestamp' => null,
                'description' => 'Extracting text from PDF...',
            ];
        } else if ($pdfDocument->status === 'failed') {
            $timeline[] = [
                'step' => 'Text Extraction',
                'status' => 'failed',
                'timestamp' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
                'description' => $pdfDocument->error_message ?? 'Text extraction failed',
            ];
        }

        // Markdown conversion step
        if ($pdfDocument->markdown_text) {
            $timeline[] = [
                'step' => 'Markdown Conversion',
                'status' => 'completed',
                'timestamp' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
                'description' => 'Text converted to markdown format',
            ];
        } else if ($pdfDocument->extracted_text && $pdfDocument->status === 'processing') {
            $timeline[] = [
                'step' => 'Markdown Conversion',
                'status' => 'processing',
                'timestamp' => null,
                'description' => 'Converting to markdown...',
            ];
        } else if ($pdfDocument->extracted_text && $pdfDocument->status === 'completed') {
            $timeline[] = [
                'step' => 'Markdown Conversion',
                'status' => 'completed',
                'timestamp' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
                'description' => 'Markdown conversion completed',
            ];
        }

        // Vector sync step
        if ($pdfDocument->vector_sync_status === 'completed') {
            $timeline[] = [
                'step' => 'Vector Database Sync',
                'status' => 'completed',
                'timestamp' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
                'description' => 'Document synced to vector database',
            ];
        } else if ($pdfDocument->vector_sync_status === 'processing') {
            $timeline[] = [
                'step' => 'Vector Database Sync',
                'status' => 'processing',
                'timestamp' => null,
                'description' => 'Syncing to vector database...',
            ];
        } else if ($pdfDocument->vector_sync_status === 'failed') {
            $timeline[] = [
                'step' => 'Vector Database Sync',
                'status' => 'failed',
                'timestamp' => $pdfDocument->updated_at->format('M d, Y H:i:s'),
                'description' => 'Vector database sync failed',
            ];
        } else if ($pdfDocument->status === 'completed') {
            $timeline[] = [
                'step' => 'Vector Database Sync',
                'status' => 'pending',
                'timestamp' => null,
                'description' => 'Waiting for vector database sync',
            ];
        }

        return $timeline;
    }
} 