<?php

namespace App\Services\PdfTextExtractor\Drivers;

use App\Services\PdfTextExtractor\Contracts\PdfDriverInterface;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;
use Smalot\PdfParser\Parser;

class SmalotPdfDriver implements PdfDriverInterface
{
    /**
     * The driver configuration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The PDF parser instance.
     *
     * @var Parser|null
     */
    protected ?Parser $parser = null;

    /**
     * Extract text from a PDF file.
     *
     * @param string $filePath
     * @return string
     * @throws PdfExtractionException
     */
    public function extract(string $filePath): string
    {
        try {
            $parser = $this->getParser();
            $pdf = $parser->parseFile($filePath);
            
            $text = $pdf->getText();
            
            // Clean up the text
            $text = $this->cleanText($text);
            
            return $text;

        } catch (\Exception $e) {
            throw PdfExtractionException::extractionFailed(
                $this->getName(),
                $filePath,
                $e->getMessage()
            );
        }
    }

    /**
     * Extract metadata from a PDF file.
     *
     * @param string $filePath
     * @return array
     * @throws PdfExtractionException
     */
    public function extractMetadata(string $filePath): array
    {
        try {
            $parser = $this->getParser();
            $pdf = $parser->parseFile($filePath);
            
            $details = $pdf->getDetails();
            
            return [
                'title' => $details['Title'] ?? null,
                'author' => $details['Author'] ?? null,
                'subject' => $details['Subject'] ?? null,
                'keywords' => $details['Keywords'] ?? null,
                'creator' => $details['Creator'] ?? null,
                'producer' => $details['Producer'] ?? null,
                'creation_date' => $details['CreationDate'] ?? null,
                'modification_date' => $details['ModDate'] ?? null,
                'pages' => count($pdf->getPages()),
                'raw_details' => $details,
            ];

        } catch (\Exception $e) {
            throw PdfExtractionException::extractionFailed(
                $this->getName(),
                $filePath,
                "Failed to extract metadata: " . $e->getMessage()
            );
        }
    }

    /**
     * Check if the driver can handle the given file.
     *
     * @param string $filePath
     * @return bool
     */
    public function canHandle(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Check if it's a PDF file
        $mimeType = mime_content_type($filePath);
        if ($mimeType !== 'application/pdf') {
            return false;
        }

        try {
            $parser = $this->getParser();
            $parser->parseFile($filePath);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'smalot';
    }

    /**
     * Get the driver configuration.
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Set the driver configuration.
     *
     * @param array $config
     * @return void
     */
    public function setConfiguration(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Get the PDF parser instance.
     *
     * @return Parser
     */
    protected function getParser(): Parser
    {
        if ($this->parser === null) {
            $this->parser = new Parser();
        }

        return $this->parser;
    }

    /**
     * Clean the extracted text.
     *
     * @param string $text
     * @return string
     */
    protected function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove excessive line breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Decode Unicode if enabled
        if ($this->config['options']['decode_unicode'] ?? true) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return trim($text);
    }
} 