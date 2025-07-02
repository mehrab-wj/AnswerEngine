<?php

namespace App\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchHtml
{
    use AsAction;

    /**
     * Fetch the HTML of a URL, caching the result for 1 day.
     *
     * @param  string  $url
     * @return string|null The HTML body, or null if the request failed.
     */
    public function handle(string $url, int $timeout = 10, int $cacheDays = 1): ?string
    {
        $url = trim($url);
        $cacheKey = $this->cacheKey($url);

        return Cache::remember($cacheKey, now()->addDays($cacheDays), function () use ($url, $timeout) {
            try {
                $response = Http::timeout($timeout)->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                // Swallow exception and return null below.
            }

            return null;
        });
    }

    protected function cacheKey(string $url): string
    {
        return 'fetch_html:' . md5($url);
    }
} 