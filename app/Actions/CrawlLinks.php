<?php

namespace App\Actions;

use Illuminate\Console\Command;
use App\Actions\FetchHtml;
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
    public string $commandSignature = 'crawl:links {url} {depth=1} {--external : Include external links in the output}';

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
     * Determine whether external links should be included in the output.
     */
    protected bool $includeExternal = false;

    /**
     * The base host used to restrict recursion.
     */
    protected string $baseHost;

    /**
     * Handle the action when executed programmatically.
     *
     * @param  string  $url   The starting URL to crawl.
     * @param  int     $depth The recursion depth (1 means current page only).
     * @return array<int, string> A flat array of discovered links.
     */
    public function handle(string $url, int $depth = 1, bool $includeExternal = false): array
    {
        $this->includeExternal = $includeExternal;

        // Remove any fragment from the starting URL and store the normalised host.
        $url = $this->stripFragment($url);
        $this->baseHost = $this->normalizeHost(parse_url($url, PHP_URL_HOST) ?? '');

        return $this->crawl($url, $depth);
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): void
    {
        $url = (string) $command->argument('url');
        $depth = (int) $command->argument('depth');
        $includeExternal = (bool) $command->option('external');

        $command->info(sprintf('Crawling %s (depth: %d) ...', $url, $depth));

        $links = $this->handle($url, $depth, $includeExternal);

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

        $crawlAi = CrawlAi::make()->handle(url: $url);

        if (!$crawlAi->success()) {
            return [];
        }
        
        $links = collect($crawlAi->internalLinks())->pluck('href')->toArray();

        foreach ($links as $link) {
            // Resolve relative links.
            if (Str::startsWith($link, '/')) {
                $link = rtrim($url, '/') . $link;
            }

            // Remove any URL fragment (everything after "#").
            $link = $this->stripFragment($link);

            if ($link === '') {
                continue;
            }

            if (! Str::startsWith($link, ['http://', 'https://'])) {
                // Skip non-HTTP links (mailto:, tel:, etc.).
                continue;
            }

            if (Str::endsWith($link, ['.txt', '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx'])) {
                continue;
            }

            $linkHost = $this->normalizeHost(parse_url($link, PHP_URL_HOST) ?? '');

            $isSameDomain = $linkHost === $this->baseHost || $linkHost === '';

            if ($isSameDomain) {
                // Always include same-domain links.
                $found[] = $link;

                // Recurse into the link if depth allows.
                if ($depth > 1) {
                    $found = array_merge($found, $this->crawl($link, $depth - 1));
                }
            } elseif ($this->includeExternal) {
                // Optionally include external links but never recurse.
                $found[] = $link;
            }
        }

        // Return unique links only.
        return array_values(array_unique($found));
    }

    /**
     * Normalise host strings (lower-case & strip leading "www.").
     */
    protected function normalizeHost(string $host): string
    {
        $host = strtolower($host);

        return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Remove any URL fragment ("#section") from a URL.
     */
    protected function stripFragment(string $url): string
    {
        // First, remove any fragment (everything after "#").
        $hashPos = strpos($url, '#');
        $url = $hashPos === false ? $url : substr($url, 0, $hashPos);

        // Then, remove any query string (everything after "?").
        $queryPos = strpos($url, '?');

        return $queryPos === false ? $url : substr($url, 0, $queryPos);
    }
} 