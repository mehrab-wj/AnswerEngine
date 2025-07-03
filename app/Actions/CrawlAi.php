<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class CrawlAi
{
    use AsAction;

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
                    'exclude_social_media_domains' => [
                        'facebook.com', 'twitter.com', 'x.com', 'linkedin.com', 'instagram.com', 'pinterest.com', 'tiktok.com', 'snapchat.com', 'reddit.com',
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
        }

        // On failure we still return the instance; accessors will return null / false accordingly.
        return $this;
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
        return $this->result['links']['internal'] ?? [];
    }

    /** External links array */
    public function externalLinks(): array
    {
        return $this->result['links']['external'] ?? [];
    }
} 