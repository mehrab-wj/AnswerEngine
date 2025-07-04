<?php

namespace App\Services\PdfTextExtractor\Drivers;

use App\Services\PdfTextExtractor\Contracts\PdfDriverInterface;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;

class NullPdfDriver implements PdfDriverInterface
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
        // For testing purposes, return empty string or throw exception based on config
        if ($this->config['simulate_failure'] ?? false) {
            throw PdfExtractionException::extractionFailed(
                $this->getName(),
                $filePath,
                'Simulated failure for testing'
            );
        }

        // Return test text if configured
        if (isset($this->config['test_text'])) {
            return $this->config['test_text'];
        }

        return '';
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
        // For testing purposes, return empty metadata or throw exception based on config
        if ($this->config['simulate_failure'] ?? false) {
            throw PdfExtractionException::extractionFailed(
                $this->getName(),
                $filePath,
                'Simulated failure for testing'
            );
        }

        // Return test metadata if configured
        if (isset($this->config['test_metadata'])) {
            return $this->config['test_metadata'];
        }

        return [
            'title' => null,
            'author' => null,
            'subject' => null,
            'keywords' => null,
            'creator' => null,
            'producer' => null,
            'creation_date' => null,
            'modification_date' => null,
            'pages' => 0,
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'file_modified' => file_exists($filePath) ? date('Y-m-d H:i:s', filemtime($filePath)) : null,
        ];
    }

    /**
     * Check if the driver can handle the given file.
     *
     * @param string $filePath
     * @return bool
     */
    public function canHandle(string $filePath): bool
    {
        // For testing purposes, always return true unless configured otherwise
        if ($this->config['simulate_cannot_handle'] ?? false) {
            return false;
        }

        // Basic file existence check
        return file_exists($filePath);
    }

    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'null';
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
} 