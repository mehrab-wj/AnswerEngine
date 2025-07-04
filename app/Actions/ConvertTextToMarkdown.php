<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class ConvertTextToMarkdown
{
    use AsAction;

    /**
     * Convert extracted text to markdown format.
     *
     * @param string $text The extracted text to convert
     * @param array $options Conversion options
     * @return string The converted markdown text
     */
    public function handle(string $text, array $options = []): string
    {
        // Get configuration options
        $config = array_merge(config('pdf.markdown', []), $options);

        // Clean the text first
        $text = $this->cleanText($text);

        // Split into lines for processing
        $lines = explode("\n", $text);

        // Process lines
        $markdownLines = [];
        $inList = false;
        $listIndent = 0;
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Skip empty lines but preserve paragraph breaks
            if (empty($line)) {
                if (!empty($markdownLines) && !empty(trim(end($markdownLines)))) {
                    $markdownLines[] = '';
                }
                $inList = false;
                continue;
            }

            // Detect headings
            if ($config['detect_headings'] ?? true) {
                $headingLevel = $this->detectHeading($line, $lines, $i, $config);
                if ($headingLevel > 0) {
                    $inList = false;
                    $markdownLines[] = str_repeat('#', $headingLevel) . ' ' . $line;
                    continue;
                }
            }

            // Detect lists
            if ($config['detect_lists'] ?? true) {
                $listInfo = $this->detectList($line);
                if ($listInfo) {
                    $inList = true;
                    $listIndent = $listInfo['indent'];
                    $markdownLines[] = str_repeat('  ', $listInfo['indent']) . $listInfo['marker'] . ' ' . $listInfo['content'];
                    continue;
                }
            }

            // Handle regular text
            if ($inList) {
                // Check if this could be a continuation of list item
                if (strlen($line) > 0 && !$this->detectList($line)) {
                    $markdownLines[] = str_repeat('  ', $listIndent + 1) . $line;
                    continue;
                }
                $inList = false;
            }

            // Regular paragraph text
            $markdownLines[] = $line;
        }

        // Join lines and clean up
        $markdown = implode("\n", $markdownLines);
        
        // Final cleanup
        $markdown = $this->finalCleanup($markdown);

        return $markdown;
    }

    /**
     * Clean the input text.
     *
     * @param string $text
     * @return string
     */
    protected function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove excessive line breaks (more than 2 consecutive)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Remove leading/trailing whitespace from each line
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        
        return implode("\n", $lines);
    }

    /**
     * Detect if a line is a heading.
     *
     * @param string $line
     * @param array $lines
     * @param int $index
     * @param array $config
     * @return int Heading level (0 if not a heading)
     */
    protected function detectHeading(string $line, array $lines, int $index, array $config): int
    {
        // Check against heading patterns
        $patterns = $config['heading_patterns'] ?? [];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                // Determine heading level based on pattern
                if (preg_match('/^[A-Z][A-Z\s]{5,}$/', $line)) {
                    return 1; // All caps - likely main heading
                } elseif (preg_match('/^\d+\.?\s+[A-Z]/', $line)) {
                    return 2; // Numbered sections
                } elseif (preg_match('/^[IVX]+\.?\s+[A-Z]/', $line)) {
                    return 2; // Roman numerals
                }
            }
        }

        // Check for potential headings by context
        if ($this->isLikelyHeading($line, $lines, $index, $config)) {
            return 2; // Default to h2 for context-based headings
        }

        return 0;
    }

    /**
     * Check if a line is likely a heading based on context.
     *
     * @param string $line
     * @param array $lines
     * @param int $index
     * @param array $config
     * @return bool
     */
    protected function isLikelyHeading(string $line, array $lines, int $index, array $config): bool
    {
        $minGap = $config['minimum_heading_gap'] ?? 2;
        
        // Check if line is short enough to be a heading (arbitrary threshold)
        if (strlen($line) > 100) {
            return false;
        }

        // Check if there's sufficient gap before and after
        $beforeGap = 0;
        $afterGap = 0;

        // Count empty lines before
        for ($i = $index - 1; $i >= 0; $i--) {
            if (trim($lines[$i]) === '') {
                $beforeGap++;
            } else {
                break;
            }
        }

        // Count empty lines after
        for ($i = $index + 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') {
                $afterGap++;
            } else {
                break;
            }
        }

        // Check if it starts with capital letter and has good spacing
        return $beforeGap >= $minGap && $afterGap >= 1 && preg_match('/^[A-Z]/', $line);
    }

    /**
     * Detect if a line is a list item.
     *
     * @param string $line
     * @return array|null List information or null if not a list
     */
    protected function detectList(string $line): ?array
    {
        // Detect numbered lists
        if (preg_match('/^(\s*)(\d+\.|\d+\))\s+(.+)$/', $line, $matches)) {
            return [
                'indent' => intval(strlen($matches[1]) / 2),
                'marker' => '1.',
                'content' => $matches[3]
            ];
        }

        // Detect bullet lists
        if (preg_match('/^(\s*)([•\-\*])\s+(.+)$/', $line, $matches)) {
            return [
                'indent' => intval(strlen($matches[1]) / 2),
                'marker' => '-',
                'content' => $matches[3]
            ];
        }

        // Detect alphabetical lists
        if (preg_match('/^(\s*)([a-z]\.)\s+(.+)$/', $line, $matches)) {
            return [
                'indent' => intval(strlen($matches[1]) / 2),
                'marker' => '1.',
                'content' => $matches[3]
            ];
        }

        return null;
    }

    /**
     * Final cleanup of the markdown text.
     *
     * @param string $markdown
     * @return string
     */
    protected function finalCleanup(string $markdown): string
    {
        // Remove excessive line breaks
        $markdown = preg_replace('/\n{4,}/', "\n\n\n", $markdown);
        
        // Ensure proper spacing around headings
        $markdown = preg_replace('/\n(#{1,6}\s+[^\n]+)\n(?!\n)/', "\n\n$1\n\n", $markdown);
        
        // Clean up list spacing
        $markdown = preg_replace('/\n(\s*[-\*\+]\s+[^\n]+)\n(?!\n|\s*[-\*\+])/', "\n$1\n", $markdown);
        
        // Remove leading/trailing whitespace
        $markdown = trim($markdown);
        
        return $markdown;
    }

    /**
     * Convert text to markdown with specific formatting rules.
     *
     * @param string $text
     * @param string $style Style preset ('basic', 'structured', 'academic')
     * @return string
     */
    public function withStyle(string $text, string $style = 'basic'): string
    {
        $options = $this->getStyleOptions($style);
        return $this->handle($text, $options);
    }

    /**
     * Get style-specific options.
     *
     * @param string $style
     * @return array
     */
    protected function getStyleOptions(string $style): array
    {
        return match ($style) {
            'structured' => [
                'detect_headings' => true,
                'detect_lists' => true,
                'minimum_heading_gap' => 1,
                'heading_patterns' => [
                    '/^[A-Z][A-Z\s]{3,}$/',
                    '/^\d+\.?\s+[A-Z]/',
                    '/^[IVX]+\.?\s+[A-Z]/',
                    '/^[A-Z][a-z]+:/',
                ],
            ],
            'academic' => [
                'detect_headings' => true,
                'detect_lists' => true,
                'minimum_heading_gap' => 2,
                'heading_patterns' => [
                    '/^[A-Z][A-Z\s]{5,}$/',
                    '/^\d+\.?\s+[A-Z]/',
                    '/^[IVX]+\.?\s+[A-Z]/',
                    '/^Abstract/',
                    '/^Introduction/',
                    '/^Conclusion/',
                    '/^References/',
                ],
            ],
            default => config('pdf.markdown', [])
        };
    }
} 