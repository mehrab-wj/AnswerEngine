<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PdfDocument;
use App\Models\ScrapeProcess;
use App\Models\ScrapeResult;
use App\Actions\CrawlWebsite;
use App\Actions\ProcessPdfDocument;
use Illuminate\Support\Facades\Auth;
use App\Services\OpenAIEmbedding;
use App\Services\VectorDatabase;
use App\Services\OpenRouter;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Fetch statistics
        $stats = $this->getStats($userId);

        // Fetch processing data
        $processingData = $this->getProcessingData($userId);

        $searchResult = $request->session()->get('searchResult');

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'processingData' => $processingData,
            'searchResult' => $searchResult,
        ]);
    }

    private function getStats(int $userId): array
    {
        // Count PDFs by status
        $pdfCounts = PdfDocument::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Count ScrapeProcesses by status
        $scrapeCounts = ScrapeProcess::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Count vector synced PDFs
        $vectorSyncedPdfs = PdfDocument::where('user_id', $userId)
            ->where('vector_sync_status', 'completed')
            ->count();

        // Count completed scrapes (consider them as synced)
        $completedScrapes = $scrapeCounts['completed'] ?? 0;

        return [
            'totalDocs' => PdfDocument::where('user_id', $userId)->count() +
                ScrapeProcess::where('user_id', $userId)->count(),
            'processing' => ($pdfCounts['processing'] ?? 0) +
                ($scrapeCounts['processing'] ?? 0),
            'vectorSynced' => $vectorSyncedPdfs + $completedScrapes,
            'totalQueries' => 0,
        ];
    }

    private function getProcessingData(int $userId): array
    {
        // Fetch PDF documents
        $pdfData = PdfDocument::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pdf) {
                return [
                    'id' => $pdf->id,
                    'uuid' => $pdf->uuid,
                    'type' => 'pdf',
                    'name' => $pdf->original_filename,
                    'status' => $pdf->status,
                    'vectorSync' => $pdf->vector_sync_status ?? 'pending',
                    'createdAt' => $pdf->created_at->format('Y-m-d H:i:s'),
                    'size' => $pdf->formatted_file_size,
                    'pages' => $pdf->page_count,
                    'processingTime' => $pdf->formatted_processing_time,
                ];
            });

        // Fetch scrape processes
        $scrapeData = ScrapeProcess::where('user_id', $userId)
            ->with('scrapeResults')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($scrape) {
                return [
                    'id' => $scrape->id,
                    'uuid' => $scrape->uuid,
                    'type' => 'website',
                    'name' => $scrape->url,
                    'status' => $scrape->status,
                    'vectorSync' => $scrape->status === 'completed' ? 'completed' : ($scrape->status === 'failed' ? 'failed' : 'pending'),
                    'createdAt' => $scrape->created_at->format('Y-m-d H:i:s'),
                    'pages' => $scrape->scrapeResults->count(),
                    'processingTime' => $scrape->status === 'completed' ?
                        $scrape->created_at->diffInSeconds($scrape->updated_at) . 's' :
                        'N/A',
                ];
            });

        // Combine and sort by creation date
        return $pdfData->concat($scrapeData)
            ->sortByDesc('createdAt')
            ->values()
            ->toArray();
    }

    /**
     * Add a new website to crawl
     */
    public function addWebsite(Request $request)
    {
        $request->validate([
            'url' => 'required|url|max:255',
            'depth' => 'required|integer|min:1|max:4'
        ]);

        $userId = Auth::id();
        $url = $request->input('url');
        $depth = $request->input('depth');

        try {
            $scrapeProcess = CrawlWebsite::make()->handle($url, $depth, $userId);
            
            return to_route('dashboard')->with('success', 'Website crawling started successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['url' => 'An error occurred while starting the crawl: ' . $e->getMessage()]);
        }
    }

    /**
     * Upload and process a PDF document
     */
    public function uploadPdf(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:51200' // 50MB max
        ]);

        $userId = Auth::id();
        $file = $request->file('file');

        try {
            ProcessPdfDocument::make()->fromUploadedFile($file, $userId);
            
            return to_route('dashboard')->with('success', 'PDF uploaded successfully and processing started!');
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'An error occurred while processing the PDF: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a source document (PDF or website)
     */
    public function deleteSource(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|in:pdf,website'
        ]);

        $userId = Auth::id();
        $id = $request->input('id');
        $type = $request->input('type');

        try {
            if ($type === 'pdf') {
                $document = PdfDocument::where('user_id', $userId)
                    ->where('id', $id)
                    ->first();

                                if (!$document) {
                    return to_route('dashboard')->with('error', 'PDF document not found or you do not have permission to delete it.');
                }
                
                $document->delete();
                
                return to_route('dashboard')->with('success', 'PDF document deleted successfully.');
            } else if ($type === 'website') {
                $scrapeProcess = ScrapeProcess::where('user_id', $userId)
                    ->where('id', $id)
                    ->first();

                                if (!$scrapeProcess) {
                    return to_route('dashboard')->with('error', 'Website scrape process not found or you do not have permission to delete it.');
                }
                
                $scrapeProcess->delete();
                
                return to_route('dashboard')->with('success', 'Website scrape process deleted successfully.');
            }
        } catch (\Exception $e) {
            return to_route('dashboard')->with('error', 'An error occurred while deleting the source: ' . $e->getMessage());
        }
    }

    /**
     * Search the knowledge base using AI embeddings
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:1000'
        ]);

        $startTime = microtime(true);
        $userId = Auth::id();
        $query = $request->input('query');

        try {
            // 1. Embed the query
            $embedding = OpenAIEmbedding::make()->embed($query);
            
            if (!$embedding) {
                return back()->withErrors(['query' => 'Failed to process your query. Please try again.']);
            }

            // 2. Query vectors in Pinecone
            $vectorResults = VectorDatabase::make()->queryVectors($embedding, 5, $userId);
            
            // 3. Format source documents
            $sourceDocuments = $this->formatSourceDocuments($vectorResults);
            
            // 4. Generate AI answer
            $aiAnswer = $this->generateAiAnswer($query, $sourceDocuments);
            
            // 5. Calculate processing time
            $processingTime = round(microtime(true) - $startTime, 2);

            // 6. Return search results
            return redirect()->back()->with([
                'searchResult' => [
                    'query' => $query,
                    'aiAnswer' => $aiAnswer,
                    'sourceDocuments' => $sourceDocuments,
                    'processingTime' => $processingTime
                ]
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['query' => 'An error occurred while searching: ' . $e->getMessage()]);
        }
    }

    /**
     * Format vector search results into source documents
     */
    private function formatSourceDocuments(array $vectorResults): array
    {
        $sourceDocuments = [];
        $processedDocuments = []; // Track processed documents to avoid duplicates
        
        if (isset($vectorResults['matches'])) {
            foreach ($vectorResults['matches'] as $match) {
                $metadata = $match['metadata'] ?? [];
                $similarity = $match['score'] ?? 0;
                
                // Determine document type and ID
                $documentType = $metadata['type'] ?? 'Unknown';
                $documentId = null;
                
                if ($documentType === 'Website' && isset($metadata['scrape_result_id'])) {
                    $documentId = $metadata['scrape_result_id'];
                    $key = "website_{$documentId}";
                    
                    // Skip if already processed, but keep highest similarity
                    if (isset($processedDocuments[$key]) && $processedDocuments[$key]['similarity'] >= $similarity) {
                        continue;
                    }
                    
                    // Get full document from database
                    $document = ScrapeResult::find($documentId);
                    if ($document) {
                        $formattedDoc = $document->toExpectedItemFormat();
                        $formattedDoc['similarity'] = $similarity;
                        $sourceDocuments[] = $formattedDoc;
                        $processedDocuments[$key] = $formattedDoc;
                    }
                } elseif ($documentType === 'PDF' && isset($metadata['pdf_document_id'])) {
                    $documentId = $metadata['pdf_document_id'];
                    $key = "pdf_{$documentId}";
                    
                    // Skip if already processed, but keep highest similarity
                    if (isset($processedDocuments[$key]) && $processedDocuments[$key]['similarity'] >= $similarity) {
                        continue;
                    }
                    
                    // Get full document from database
                    $document = PdfDocument::find($documentId);
                    if ($document) {
                        $formattedDoc = $document->toExpectedItemFormat();
                        $formattedDoc['similarity'] = $similarity;
                        $sourceDocuments[] = $formattedDoc;
                        $processedDocuments[$key] = $formattedDoc;
                    }
                }
            }
        }
        
        // Sort by similarity descending and return unique documents
        usort($sourceDocuments, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return $sourceDocuments;
    }

    /**
     * Generate AI answer using retrieved context
     */
    private function generateAiAnswer(string $query, array $sourceDocuments): string
    {
        if (empty($sourceDocuments)) {
            return "I couldn't find any relevant information in your knowledge base to answer this question.";
        }

        // Build context from source documents
        $context = '';
        foreach ($sourceDocuments as $doc) {
            $source = $doc['content_type'] === 'pdf' 
                ? ($doc['filename'] ?? 'Unknown PDF') 
                : ($doc['source_url'] ?? 'Unknown Website');
            $context .= "Source: {$source}\n";
            $context .= "Content: {$doc['content']}\n\n";
        }

        $prompt = "Based on the following context from the user's knowledge base, please provide a helpful and accurate answer to user question.\n\n";
        $prompt .= "If the answer of the question is not in the context, please say so. Don't make up an answer.\n";
        $prompt .= "Please add refrences to the context in the answers you make.\n";
        $prompt .= "<context>\n{$context}\n</context>";

        try {
            $client = OpenRouter::make();
            $response = $client->chat()->create([
                'model' => 'openai/gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $query]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]);

            return trim($response->choices[0]->message->content);
        } catch (\Exception $e) {
            return "I found relevant information but couldn't generate an answer at this time. Please check the source documents below.";
        }
    }
}
