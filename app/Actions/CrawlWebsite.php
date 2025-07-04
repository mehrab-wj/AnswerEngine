<?php

namespace App\Actions;

use App\Jobs\CrawlPageJob;
use App\Models\ScrapeProcess;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CrawlWebsite
{
    use AsAction;

    public string $commandSignature = 'crawl:website {url} {depth=1} {--user=1 : User ID for the crawl process}';
    public string $commandDescription = 'Crawl a website and save all discovered content to the database.';

    /**
     * Handle the website crawling process.
     *
     * @param  string  $url     The starting URL to crawl.
     * @param  int     $depth   The recursion depth (1 means current page only).
     * @param  int     $userId  The user ID who initiated the crawl.
     * @return ScrapeProcess    The created scrape process.
     */
    public function handle(string $url, int $depth = 1, int $userId = 1): ScrapeProcess
    {
        // Normalize the URL
        $url = $this->normalizeUrl($url);

        // Create the scrape process
        $scrapeProcess = ScrapeProcess::create([
            'url' => $url,
            'status' => 'pending',
            'user_id' => $userId,
        ]);

        // Mark as processing
        $scrapeProcess->markAsProcessing();

        // Initialize the cache for processed URLs
        $this->initializeProcessedUrls($scrapeProcess->id);

        // Dispatch the first job
        CrawlPageJob::dispatch($scrapeProcess->id, $url, $depth, $userId);

        return $scrapeProcess;
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $url = (string) $command->argument('url');
        $depth = (int) $command->argument('depth');
        $userId = (int) $command->option('user');

        $command->info(sprintf('Starting crawl of %s (depth: %d) for user %d...', $url, $depth, $userId));

        $scrapeProcess = $this->handle($url, $depth, $userId);

        $command->info(sprintf('Crawl process created with UUID: %s', $scrapeProcess->uuid));
        $command->info('Jobs have been dispatched to the queue. Monitor the process status.');
    }

    /**
     * Normalize URL by removing fragments and query strings.
     */
    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        
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
     * Initialize the cache for tracking processed URLs.
     */
    protected function initializeProcessedUrls(int $processId): void
    {
        cache()->put("scrape_process_{$processId}_processed_urls", [], now()->addHours(24));
        
        // Initialize job tracking counters
        cache()->put("scrape_process_{$processId}_job_count", 1, now()->addHours(24)); // Start with 1 for the initial job
        cache()->put("scrape_process_{$processId}_completed_jobs", 0, now()->addHours(24));
    }
} 