<?php

namespace App\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CrawlLinks
{
    use AsAction;

    /**
     * The artisan command signature.
     *
     * Example usage:
     *   php artisan crawl:links https://example.com 2
     */
    public string $commandSignature = 'crawl:links {url} {depth=1}';

    /**
     * The command description that will appear in `php artisan list`.
     */
    public string $commandDescription = 'Crawl a URL and list all discovered links up to the given depth.';

    /**
     * Visited URLs to avoid infinite recursion.
     *
     * @var array<string, bool>
     */
    protected array $visited = [];

    /**
     * Handle the action when executed programmatically.
     *
     * @param  string  $url   The starting URL to crawl.
     * @param  int     $depth The recursion depth (1 means current page only).
     * @return array<int, string> A flat array of discovered links.
     */
    public function handle(string $url, int $depth = 1): array
    {
        return $this->crawl($url, $depth);
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $url = (string) $command->argument('url');
        $depth = (int) $command->argument('depth');

        $command->info(sprintf('Crawling %s (depth: %d) ...', $url, $depth));

        $links = $this->handle($url, $depth);

        if (empty($links)) {
            $command->warn('No links were found.');
            return;
        }

        $command->line('--- Discovered links ---');
        foreach ($links as $link) {
            $command->line($link);
        }

        $command->info(sprintf('Finished. %d unique link( s) found.', count($links)));
    }

    /**
     * Crawl a given URL recursively to the provided depth and return the links found.
     *
     * @param  string  $url
     * @param  int     $depth
     * @return array<int, string>
     */
    protected function crawl(string $url, int $depth): array
    {
        // Guard clauses.
        if ($depth < 1) {
            return [];
        }

        // Prevent revisiting the same URL.
        if (isset($this->visited[$url])) {
            return [];
        }

        $this->visited[$url] = true;

        try {
            $response = Http::timeout(10)->get($url);

            if ($response->failed()) {
                return [];
            }
        } catch (\Throwable $e) {
            // Skip URLs that cannot be fetched.
            return [];
        }

        $html = $response->body();

        // Extract links using a simple regex.
        // Note: For a production-ready crawler, consider using a proper HTML parser instead.
        preg_match_all('/<a\s+[^>]*href=["\\\']([^"\\\']+)["\\\']/i', $html, $matches);

        $found = [];

        foreach ($matches[1] as $link) {
            // Resolve relative links.
            if (Str::startsWith($link, '/')) {
                $link = rtrim($url, '/') . $link;
            }

            if (! Str::startsWith($link, ['http://', 'https://'])) {
                // Skip non-HTTP links (mailto:, tel:, etc.).
                continue;
            }

            $found[] = $link;

            // Recurse into the link if depth allows.
            if ($depth > 1) {
                $found = array_merge($found, $this->crawl($link, $depth - 1));
            }
        }

        // Return unique links only.
        return array_values(array_unique($found));
    }
} 