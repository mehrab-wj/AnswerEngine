<?php

namespace App\Services\PdfTextExtractor\Contracts;

interface PdfDriverInterface
{
    /**
     * Extract text from a PDF file.
     *
     * @param string $filePath The path to the PDF file
     * @return string The extracted text
     * @throws \App\Services\PdfTextExtractor\Exceptions\PdfExtractionException
     */
    public function extract(string $filePath): string;

    /**
     * Extract metadata from a PDF file.
     *
     * @param string $filePath The path to the PDF file
     * @return array The extracted metadata
     * @throws \App\Services\PdfTextExtractor\Exceptions\PdfExtractionException
     */
    public function extractMetadata(string $filePath): array;

    /**
     * Check if the driver can handle the given file.
     *
     * @param string $filePath The path to the PDF file
     * @return bool True if the driver can handle the file
     */
    public function canHandle(string $filePath): bool;

    /**
     * Get the driver name.
     *
     * @return string The driver name
     */
    public function getName(): string;

    /**
     * Get the driver configuration.
     *
     * @return array The driver configuration
     */
    public function getConfiguration(): array;

    /**
     * Set the driver configuration.
     *
     * @param array $config The driver configuration
     * @return void
     */
    public function setConfiguration(array $config): void;
} 