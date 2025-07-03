<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class ChunkMarkdown
{
    use AsAction;

    public function handle(string $markdownContent, int $maxChunkSize = 350, int $minChunkSize = 250, int $overlapSize = 40): array
    {

        // Parse markdown into logical sections
        $sections = $this->parseMarkdownStructure($markdownContent);
        
        // Create chunks from sections
        $chunks = $this->createChunks($sections, $maxChunkSize, $minChunkSize);
        
        // Add overlap between chunks
        $chunksWithOverlap = $this->addOverlap($chunks, $overlapSize);
        
        return $chunksWithOverlap;
    }

    private function parseMarkdownStructure(string $content): array
    {
        $lines = explode("\n", $content);
        $sections = [];
        $currentSection = [];
        $currentHeader = '';
        $currentHeaderLevel = 0;
        $inCodeBlock = false;
        $codeBlockFence = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Handle code blocks
            if (preg_match('/^```/', $trimmedLine) || preg_match('/^~~~/', $trimmedLine)) {
                if (!$inCodeBlock) {
                    $inCodeBlock = true;
                    $codeBlockFence = substr($trimmedLine, 0, 3);
                } elseif (strpos($trimmedLine, $codeBlockFence) === 0) {
                    $inCodeBlock = false;
                    $codeBlockFence = '';
                }
                $currentSection[] = $line;
                continue;
            }

            if ($inCodeBlock) {
                $currentSection[] = $line;
                continue;
            }

            // Check for headers
            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmedLine, $matches)) {
                $headerLevel = strlen($matches[1]);
                $headerText = $matches[2];

                // If we have content and this is a new section, save current section
                if (!empty($currentSection)) {
                    $sections[] = [
                        'header' => $currentHeader,
                        'header_level' => $currentHeaderLevel,
                        'content' => implode("\n", $currentSection),
                        'type' => 'section'
                    ];
                    $currentSection = [];
                }

                $currentHeader = $headerText;
                $currentHeaderLevel = $headerLevel;
                $currentSection[] = $line; // Include the header line
            } else {
                $currentSection[] = $line;
            }
        }

        // Add final section
        if (!empty($currentSection)) {
            $sections[] = [
                'header' => $currentHeader,
                'header_level' => $currentHeaderLevel,
                'content' => implode("\n", $currentSection),
                'type' => 'section'
            ];
        }

        return $sections;
    }

    private function createChunks(array $sections, int $maxChunkSize, int $minChunkSize): array
    {
        $chunks = [];
        $currentChunk = '';
        $currentChunkWordCount = 0;

        foreach ($sections as $section) {
            $sectionContent = $section['content'];
            $sectionWordCount = $this->countWords($sectionContent);
            
            // If section alone exceeds max size, split it
            if ($sectionWordCount > $maxChunkSize) {
                // Save current chunk if it has content
                if (!empty($currentChunk)) {
                    $chunks[] = [
                        'content' => trim($currentChunk),
                        'word_count' => $currentChunkWordCount,
                        'metadata' => [
                            'type' => 'section_group'
                        ]
                    ];
                    $currentChunk = '';
                    $currentChunkWordCount = 0;
                }

                // Split large section
                $splitChunks = $this->splitLargeSection($section, $maxChunkSize);
                $chunks = array_merge($chunks, $splitChunks);
                continue;
            }

            // Check if adding this section would exceed max size
            if ($currentChunkWordCount + $sectionWordCount > $maxChunkSize) {
                // Save current chunk if it meets minimum size
                if ($currentChunkWordCount >= $minChunkSize) {
                    $chunks[] = [
                        'content' => trim($currentChunk),
                        'word_count' => $currentChunkWordCount,
                        'metadata' => [
                            'type' => 'section_group'
                        ]
                    ];
                    $currentChunk = '';
                    $currentChunkWordCount = 0;
                }
            }

            // Add section to current chunk
            $currentChunk .= ($currentChunk ? "\n\n" : '') . $sectionContent;
            $currentChunkWordCount += $sectionWordCount;
        }

        // Add final chunk
        if (!empty($currentChunk)) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'word_count' => $currentChunkWordCount,
                'metadata' => [
                    'type' => 'section_group'
                ]
            ];
        }

        return $chunks;
    }

    private function splitLargeSection(array $section, int $maxChunkSize): array
    {
        $chunks = [];
        $content = $section['content'];
        $paragraphs = preg_split('/\n\s*\n/', $content);
        
        $currentChunk = '';
        $currentWordCount = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraphWordCount = $this->countWords($paragraph);
            
            // If single paragraph exceeds max size, split by sentences
            if ($paragraphWordCount > $maxChunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = [
                        'content' => trim($currentChunk),
                        'word_count' => $currentWordCount,
                        'metadata' => [
                            'type' => 'large_section_split'
                        ]
                    ];
                    $currentChunk = '';
                    $currentWordCount = 0;
                }

                // Split by sentences
                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                $sentenceChunk = '';
                $sentenceWordCount = 0;

                foreach ($sentences as $sentence) {
                    $sentenceWords = $this->countWords($sentence);
                    
                    if ($sentenceWordCount + $sentenceWords > $maxChunkSize && !empty($sentenceChunk)) {
                        $chunks[] = [
                            'content' => trim($sentenceChunk),
                            'word_count' => $sentenceWordCount,
                            'metadata' => [
                                'type' => 'sentence_split'
                            ]
                        ];
                        $sentenceChunk = '';
                        $sentenceWordCount = 0;
                    }

                    $sentenceChunk .= ($sentenceChunk ? ' ' : '') . $sentence;
                    $sentenceWordCount += $sentenceWords;
                }

                if (!empty($sentenceChunk)) {
                    $currentChunk = $sentenceChunk;
                    $currentWordCount = $sentenceWordCount;
                }
                continue;
            }

            // Check if adding paragraph exceeds max size
            if ($currentWordCount + $paragraphWordCount > $maxChunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = [
                        'content' => trim($currentChunk),
                        'word_count' => $currentWordCount,
                        'metadata' => [
                            'type' => 'large_section_split'
                        ]
                    ];
                    $currentChunk = '';
                    $currentWordCount = 0;
                }
            }

            $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
            $currentWordCount += $paragraphWordCount;
        }

        // Add final chunk
        if (!empty($currentChunk)) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'word_count' => $currentWordCount,
                'metadata' => [
                    'type' => 'large_section_split'
                ]
            ];
        }

        return $chunks;
    }

    private function addOverlap(array $chunks, int $overlapSize): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $chunksWithOverlap = [];

        foreach ($chunks as $index => $chunk) {
            $content = $chunk['content'];
            $wordCount = $chunk['word_count'];
            $overlapStart = '';
            $overlapEnd = '';

            // Add overlap from previous chunk
            if ($index > 0) {
                $overlapStart = $this->getLastWords($chunks[$index - 1]['content'], $overlapSize);
            }

            // Add overlap to next chunk
            if ($index < count($chunks) - 1) {
                $overlapEnd = $this->getFirstWords($chunks[$index + 1]['content'], $overlapSize);
            }

            // Combine content with overlaps
            $finalContent = trim($overlapStart . ($overlapStart ? "\n\n" : '') . $content . ($overlapEnd ? "\n\n" : '') . $overlapEnd);

            $chunksWithOverlap[] = [
                'content' => $finalContent,
                'word_count' => $wordCount,
                'total_word_count' => $this->countWords($finalContent),
                'metadata' => array_merge($chunk['metadata'], [
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                    'has_overlap_start' => !empty($overlapStart),
                    'has_overlap_end' => !empty($overlapEnd),
                    'overlap_size' => $overlapSize
                ])
            ];
        }

        return $chunksWithOverlap;
    }

    private function countWords(string $content): int
    {
        // Strip markdown syntax for counting
        $plaintext = $this->stripMarkdownSyntax($content);
        
        // Count words
        $words = preg_split('/\s+/', trim($plaintext), -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    private function stripMarkdownSyntax(string $content): string
    {
        // Remove HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        
        // Remove code blocks (but keep content)
        $content = preg_replace('/^```[\w]*\n?(.*?)\n?```$/ms', '$1', $content);
        $content = preg_replace('/^~~~[\w]*\n?(.*?)\n?~~~$/ms', '$1', $content);
        
        // Remove inline code backticks
        $content = preg_replace('/`([^`]+)`/', '$1', $content);
        
        // Remove headers (keep text)
        $content = preg_replace('/^#{1,6}\s+(.+)$/m', '$1', $content);
        
        // Remove emphasis (keep text)
        $content = preg_replace('/\*\*([^*]+)\*\*/', '$1', $content);
        $content = preg_replace('/\*([^*]+)\*/', '$1', $content);
        $content = preg_replace('/__([^_]+)__/', '$1', $content);
        $content = preg_replace('/_([^_]+)_/', '$1', $content);
        
        // Remove links (keep text)
        $content = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $content);
        
        // Remove list markers
        $content = preg_replace('/^\s*[-*+]\s+/m', '', $content);
        $content = preg_replace('/^\s*\d+\.\s+/m', '', $content);
        
        // Remove blockquotes
        $content = preg_replace('/^>\s*/m', '', $content);
        
        // Remove horizontal rules
        $content = preg_replace('/^---+$/m', '', $content);
        $content = preg_replace('/^\*\*\*+$/m', '', $content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }

    private function getFirstWords(string $content, int $wordCount): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        $firstWords = array_slice($words, 0, $wordCount);
        return implode(' ', $firstWords);
    }

    private function getLastWords(string $content, int $wordCount): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        $lastWords = array_slice($words, -$wordCount);
        return implode(' ', $lastWords);
    }
}
