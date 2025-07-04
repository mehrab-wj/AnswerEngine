<?php

namespace App\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Str;

class CrawlAi
{
    use AsAction;

    public string $commandSignature = "crawl {url} {accessor=markdown} {--stream : Whether to request streaming (defaults to false for simpler parsing)} {--cache-days=1 : Number of days to cache the result}";
    public string $commandDescription = "Crawl a URL using crawl4ai and return the specified accessor result.";

    /**
     * Base endpoint for the crawl4ai API (without trailing slash).
     */
    public string $endpoint = 'http://crawl4ai.forgelink.co:11235';

    /**
     * The decoded first result from the API response.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $result = null;

    /**
     * Fetch HTML content for the given URL via crawl4ai.
     *
     * @param  string  $url      Absolute URL to crawl.
     * @param  int     $timeout  HTTP timeout in seconds.
     * @param  bool    $stream   Whether to request streaming (defaults to false for simpler parsing).
     * @param  int     $cacheDays  Number of days to cache the result.
     * @return $this         Allows method chaining and access to accessor helpers.
     */
    public function handle(string $url, int $timeout = 10, bool $stream = false, int $cacheDays = 1): self
    {
        $url = trim($url);
        $cacheKey = $this->cacheKey($url, $stream);

        // Attempt to retrieve cached result first
        if (Cache::has($cacheKey)) {
            $this->result = Cache::get($cacheKey);
            return $this;
        }

        $payload = [
            'urls' => [$url],
            'crawler_config' => [
                'type'   => 'CrawlerRunConfig',
                'params' => [
                    'scraping_strategy' => [
                        'type'   => 'WebScrapingStrategy',
                        'params' => new \stdClass(),
                    ],
                    'exclude_all_images' => true,
                    'exclude_social_media_domains' => [
                        'facebook.com',
                        'twitter.com',
                        'x.com',
                        'linkedin.com',
                        'instagram.com',
                        'pinterest.com',
                        'tiktok.com',
                        'snapchat.com',
                        'reddit.com',
                    ],
                    'stream' => $stream,
                ],
            ],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint . '/crawl', $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['results'][0])) {
                    $result = $data['results'][0];

                    $this->result = $result;

                    // Cache the successful result for future requests
                    Cache::put($cacheKey, $result, now()->addDays($cacheDays));
                    return $this;
                }
            }
        } catch (\Throwable $e) {
            // Silent failure: return null so caller can decide what to do.
            echo PHP_EOL . $e->getMessage() . PHP_EOL;
        }

        // On failure we still return the instance; accessors will return null / false accordingly.
        return $this;
    }

    public function asCommand(Command $command): void
    {
        $command->info("Crawling {$command->argument('url')}");
        $this->handle($command->argument('url'), $command->option('stream'), $command->option('cache-days'));

        if (!$this->success()) {
            $command->error("Failed to crawl {$command->argument('url')}");
            return;
        }

        switch ($command->argument('accessor')) {
            case 'markdown':
                $command->line($this->rawMarkdown());
                break;
            case 'html':
                $command->line($this->html());
                break;
            case 'cleaned_html':
                $command->line($this->cleanedHtml());
                break;
            default:
                $command->info("Supported accessors: markdown, html, cleaned_html");
        }
    }

    /**
     * Generate a cache key for the given URL and stream flag.
     */
    protected function cacheKey(string $url, bool $stream): string
    {
        return 'crawl_ai:' . md5($url . '|' . ($stream ? '1' : '0'));
    }

    /* -------------------------------------------------
     |  Accessors
     | -------------------------------------------------*/

    /** Determine if the API call succeeded */
    public function success(): bool
    {
        return isset($this->result) && $this->result['success'];
    }

    /** Raw HTML returned by crawl4ai */
    public function html(): ?string
    {
        return $this->result['html'] ?? null;
    }

    /** Cleaned HTML */
    public function cleanedHtml(): ?string
    {
        return $this->result['cleaned_html'] ?? null;
    }

    /** Raw markdown */
    public function rawMarkdown(): ?string
    {
        return $this->result['markdown']['raw_markdown'] ?? null;
    }

    /** Internal links array */
    public function internalLinks(): array
    {
        $links = collect($this->result['links']['internal'] ?? [])
            ->map(function ($link) {
                return [
                    'href' => $this->cleanUrl($link['href']),
                    'text' => $link['text'] ?? $link['href'],
                    'title' => $link['title'] ?? $link['href'],
                ];
            })
            ->unique('href') // Remove duplicates based on href
            ->values() // Re-index the array
            ->toArray();

        return $links;
    }

    /** External links array */
    public function externalLinks(): array
    {
        $links = collect($this->result['links']['external'] ?? [])
            ->map(function ($link) {
                return [
                    'href' => $this->cleanUrl($link['href']),
                    'text' => $link['text'] ?? $link['href'],
                    'title' => $link['title'] ?? $link['href'],
                ];
            })
            ->unique('href') // Remove duplicates based on href
            ->values() // Re-index the array
            ->toArray();

        return $links;
    }

    /** 
     * Clean URL by removing fragments, query strings, and trailing slashes 
     */
    protected function cleanUrl(string $url): string
    {
        // Remove fragments (everything after "#")
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $url = substr($url, 0, $hashPos);
        }

        // Remove query strings (everything after "?")
        $queryPos = strpos($url, '?');
        if ($queryPos !== false) {
            $url = substr($url, 0, $queryPos);
        }

        // Remove trailing slashes
        $url = rtrim($url, '/');

        return $url;
    }
}
