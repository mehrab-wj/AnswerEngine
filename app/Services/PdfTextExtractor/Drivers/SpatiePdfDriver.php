<?php

namespace App\Services\PdfTextExtractor\Drivers;

use App\Services\PdfTextExtractor\Contracts\PdfDriverInterface;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;
use Spatie\PdfToText\Pdf;

class SpatiePdfDriver implements PdfDriverInterface
{
    /**
     * The driver configuration.
     *
     * @var array
     */
    protected array $config = [];

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
            $pdf = $this->createPdfInstance($filePath);
            $text = $pdf->text();
            
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
            // For Spatie, we'll use a basic approach since it focuses on text extraction
            // We can use pdfinfo command if available, or fall back to file stats
            
            $metadata = [
                'title' => null,
                'author' => null,
                'subject' => null,
                'keywords' => null,
                'creator' => null,
                'producer' => null,
                'creation_date' => null,
                'modification_date' => null,
                'pages' => null,
                'file_size' => filesize($filePath),
                'file_modified' => date('Y-m-d H:i:s', filemtime($filePath)),
            ];

            // Try to get additional metadata using pdfinfo if available
            if ($this->isPdfinfoAvailable()) {
                $metadata = array_merge($metadata, $this->extractPdfinfoMetadata($filePath));
            }

            return $metadata;

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

        // Check if pdftotext binary is available
        if (!$this->isPdftotextAvailable()) {
            return false;
        }

        try {
            $pdf = $this->createPdfInstance($filePath);
            $pdf->text();
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
        return 'spatie';
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
     * Create a PDF instance.
     *
     * @param string $filePath
     * @return Pdf
     * @throws PdfExtractionException
     */
    protected function createPdfInstance(string $filePath): Pdf
    {
        $binaryPath = $this->getBinaryPath();
        
        if (!$binaryPath) {
            throw new PdfExtractionException("pdftotext binary not found for Spatie driver");
        }

        $pdf = new Pdf($binaryPath);
        
        // Set options if configured
        $options = $this->getOptions();
        if ($options) {
            $pdf->setOptions($options);
        }

        return $pdf;
    }

    /**
     * Get the binary path for pdftotext.
     *
     * @return string|null
     */
    protected function getBinaryPath(): ?string
    {
        $binaryPath = $this->config['binary_path'] ?? null;
        
        if ($binaryPath && file_exists($binaryPath)) {
            return $binaryPath;
        }

        // Try to find pdftotext in common locations
        $commonPaths = [
            '/usr/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/opt/homebrew/bin/pdftotext', // macOS with Homebrew
            'pdftotext', // System PATH
        ];

        foreach ($commonPaths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get the options for pdftotext.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        $options = $this->config['options'] ?? '';
        
        if (is_string($options)) {
            // Parse string options into array
            return explode(' ', trim($options));
        }

        return is_array($options) ? $options : [];
    }

    /**
     * Check if pdftotext binary is available.
     *
     * @return bool
     */
    protected function isPdftotextAvailable(): bool
    {
        return $this->getBinaryPath() !== null;
    }

    /**
     * Check if pdfinfo binary is available.
     *
     * @return bool
     */
    protected function isPdfinfoAvailable(): bool
    {
        return $this->commandExists('pdfinfo');
    }

    /**
     * Check if a command exists.
     *
     * @param string $command
     * @return bool
     */
    protected function commandExists(string $command): bool
    {
        $return = shell_exec("command -v $command 2>/dev/null");
        return !empty($return);
    }

    /**
     * Extract metadata using pdfinfo.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractPdfinfoMetadata(string $filePath): array
    {
        try {
            $output = shell_exec("pdfinfo " . escapeshellarg($filePath) . " 2>/dev/null");
            
            if (!$output) {
                return [];
            }

            $metadata = [];
            $lines = explode("\n", trim($output));
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    [$key, $value] = explode(':', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    switch ($key) {
                        case 'Title':
                            $metadata['title'] = $value;
                            break;
                        case 'Author':
                            $metadata['author'] = $value;
                            break;
                        case 'Subject':
                            $metadata['subject'] = $value;
                            break;
                        case 'Keywords':
                            $metadata['keywords'] = $value;
                            break;
                        case 'Creator':
                            $metadata['creator'] = $value;
                            break;
                        case 'Producer':
                            $metadata['producer'] = $value;
                            break;
                        case 'CreationDate':
                            $metadata['creation_date'] = $value;
                            break;
                        case 'ModDate':
                            $metadata['modification_date'] = $value;
                            break;
                        case 'Pages':
                            $metadata['pages'] = (int) $value;
                            break;
                    }
                }
            }

            return $metadata;

        } catch (\Exception $e) {
            return [];
        }
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
        
        return trim($text);
    }
} 