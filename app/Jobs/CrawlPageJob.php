<?php

namespace App\Jobs;

use App\Actions\CrawlAi;
use App\Models\ScrapeProcess;
use App\Models\ScrapeResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrawlPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $scrapeProcessId;
    public string $url;
    public int $remainingDepth;
    public int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $scrapeProcessId, string $url, int $remainingDepth, int $userId)
    {
        $this->scrapeProcessId = $scrapeProcessId;
        $this->url = $url;
        $this->remainingDepth = $remainingDepth;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scrapeProcess = ScrapeProcess::find($this->scrapeProcessId);
        
        if (!$scrapeProcess) {
            return;
        }

        // Check if URL was already processed
        if ($this->isUrlProcessed($this->url)) {
            return;
        }

        // Mark URL as processed
        $this->markUrlAsProcessed($this->url);

        try {
            // Crawl the page
            $crawlAi = CrawlAi::make()->handle($this->url);

            if (!$crawlAi->success()) {
                Log::warning("Failed to crawl URL: {$this->url}");
                return;
            }

            // Save the page content
            $this->savePageContent($crawlAi);

            // If depth allows, dispatch jobs for internal links
            if ($this->remainingDepth > 1) {
                $this->dispatchLinksJobs($crawlAi);
            }

        } catch (\Exception $e) {
            Log::error("Error crawling {$this->url}: " . $e->getMessage());
        }

        // Check if all jobs are done and update process status
        $this->checkProcessCompletion();
    }

    /**
     * Check if URL was already processed.
     */
    protected function isUrlProcessed(string $url): bool
    {
        $processedUrls = Cache::get("scrape_process_{$this->scrapeProcessId}_processed_urls", []);
        return in_array($url, $processedUrls);
    }

    /**
     * Mark URL as processed.
     */
    protected function markUrlAsProcessed(string $url): void
    {
        $cacheKey = "scrape_process_{$this->scrapeProcessId}_processed_urls";
        $processedUrls = Cache::get($cacheKey, []);
        $processedUrls[] = $url;
        Cache::put($cacheKey, $processedUrls, now()->addHours(24));
    }

    /**
     * Save page content as ScrapeResult.
     */
    protected function savePageContent(CrawlAi $crawlAi): void
    {
        // Extract title from markdown (first # heading) or use URL as fallback
        $title = $this->extractTitle($crawlAi->rawMarkdown()) ?: $this->url;

        ScrapeResult::create([
            'scrape_process_id' => $this->scrapeProcessId,
            'user_id' => $this->userId,
            'title' => $title,
            'content' => $crawlAi->rawMarkdown() ?? '',
            'source_url' => $this->url,
            'author' => null, // Could be extracted from metadata in future
            'internal_links' => $crawlAi->internalLinks(),
            'external_links' => $crawlAi->externalLinks(),
        ]);
    }

    /**
     * Extract title from markdown content.
     */
    protected function extractTitle(?string $markdown): ?string
    {
        if (!$markdown) {
            return null;
        }

        // Look for first # heading
        if (preg_match('/^# (.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        // Look for first ## heading
        if (preg_match('/^## (.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Check if the entire process is completed.
     */
    protected function checkProcessCompletion(): void
    {
        $scrapeProcess = ScrapeProcess::find($this->scrapeProcessId);
        
        if (!$scrapeProcess || !$scrapeProcess->isProcessing()) {
            return;
        }

        // Use atomic operations to track job completion
        $cacheKey = "scrape_process_{$this->scrapeProcessId}_job_count";
        $completedKey = "scrape_process_{$this->scrapeProcessId}_completed_jobs";
        
        // Increment completed jobs counter
        $completedJobs = Cache::increment($completedKey, 1);
        
        // Get total jobs dispatched (this is set when dispatching new jobs)
        $totalJobs = Cache::get($cacheKey, 1); // Default to 1 for the initial job
        
        // If all jobs are completed, mark the process as completed
        if ($completedJobs >= $totalJobs) {
            $scrapeProcess->markAsCompleted();
            
            // Clean up cache
            Cache::forget($cacheKey);
            Cache::forget($completedKey);
            Cache::forget("scrape_process_{$this->scrapeProcessId}_processed_urls");
        }
    }

    /**
     * Dispatch jobs for internal links.
     */
    protected function dispatchLinksJobs(CrawlAi $crawlAi): void
    {
        $baseUrl = $this->url;
        $baseHost = $this->normalizeHost(parse_url($baseUrl, PHP_URL_HOST) ?? '');
        $jobsToDispatch = [];

        foreach ($crawlAi->internalLinks() as $linkData) {
            $link = $linkData['href'] ?? null;
            
            if (!$link) {
                continue;
            }

            // Process the link
            $processedLink = $this->processLink($link, $baseUrl, $baseHost);
            
            if ($processedLink && !$this->isUrlProcessed($processedLink)) {
                $jobsToDispatch[] = $processedLink;
            }
        }

        // Update the total job count before dispatching
        if (!empty($jobsToDispatch)) {
            $cacheKey = "scrape_process_{$this->scrapeProcessId}_job_count";
            Cache::increment($cacheKey, count($jobsToDispatch));
            
            // Dispatch all jobs
            foreach ($jobsToDispatch as $link) {
                self::dispatch($this->scrapeProcessId, $link, $this->remainingDepth - 1, $this->userId);
            }
        }
    }

    /**
     * Process and validate a link.
     */
    protected function processLink(string $link, string $baseUrl, string $baseHost): ?string
    {
        // Resolve relative links
        if (Str::startsWith($link, '/')) {
            $link = rtrim($baseUrl, '/') . $link;
        }

        // Remove fragment and query string
        $link = $this->normalizeUrl($link);

        if (empty($link)) {
            return null;
        }

        // Skip non-HTTP links
        if (!Str::startsWith($link, ['http://', 'https://'])) {
            return null;
        }

        // Skip file downloads
        if (Str::endsWith($link, ['.txt', '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx'])) {
            return null;
        }

        // Check if it's same domain
        $linkHost = $this->normalizeHost(parse_url($link, PHP_URL_HOST) ?? '');
        if ($linkHost !== $baseHost && !empty($linkHost)) {
            return null; // Skip external links
        }

        return $link;
    }

    /**
     * Normalize URL by removing fragments and query strings.
     */
    protected function normalizeUrl(string $url): string
    {
        // Remove fragment (everything after "#")
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $url = substr($url, 0, $hashPos);
        }

        // Remove query string (everything after "?")
        $queryPos = strpos($url, '?');
        if ($queryPos !== false) {
            $url = substr($url, 0, $queryPos);
        }

        return $url;
    }

    /**
     * Normalize host strings (lowercase & strip leading "www.").
     */
    protected function normalizeHost(string $host): string
    {
        $host = strtolower($host);
        return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CrawlPageJob failed for URL {$this->url}: " . $exception->getMessage());
        
        // Don't mark the entire process as failed for individual page failures
        // The process can still continue with other pages
    }
} 