<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PdfDocument;
use App\Models\ScrapeProcess;
use App\Actions\CrawlWebsite;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // Fetch statistics
        $stats = $this->getStats($userId);

        // Fetch processing data
        $processingData = $this->getProcessingData($userId);

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'processingData' => $processingData,
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
            'totalQueries' => 156, // Placeholder - can be implemented later
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
}
