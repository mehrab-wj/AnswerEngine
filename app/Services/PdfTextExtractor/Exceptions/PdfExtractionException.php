<?php

namespace App\Services\PdfTextExtractor\Exceptions;

use Exception;

class PdfExtractionException extends Exception
{
    /**
     * The driver that failed.
     *
     * @var string|null
     */
    protected $driver;

    /**
     * The file path that failed.
     *
     * @var string|null
     */
    protected $filePath;

    /**
     * Create a new PDF extraction exception.
     *
     * @param string $message
     * @param string|null $driver
     * @param string|null $filePath
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(
        string $message = '',
        ?string $driver = null,
        ?string $filePath = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->driver = $driver;
        $this->filePath = $filePath;
    }

    /**
     * Get the driver that failed.
     *
     * @return string|null
     */
    public function getDriver(): ?string
    {
        return $this->driver;
    }

    /**
     * Get the file path that failed.
     *
     * @return string|null
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Create a driver not found exception.
     *
     * @param string $driver
     * @return static
     */
    public static function driverNotFound(string $driver): static
    {
        return new static("PDF extraction driver '{$driver}' not found.", $driver);
    }

    /**
     * Create a file not found exception.
     *
     * @param string $filePath
     * @return static
     */
    public static function fileNotFound(string $filePath): static
    {
        return new static("PDF file not found: {$filePath}", null, $filePath);
    }

    /**
     * Create a file too large exception.
     *
     * @param string $filePath
     * @param int $size
     * @param int $maxSize
     * @return static
     */
    public static function fileTooLarge(string $filePath, int $size, int $maxSize): static
    {
        return new static(
            "PDF file too large: {$size}MB exceeds maximum of {$maxSize}MB",
            null,
            $filePath
        );
    }

    /**
     * Create an invalid file exception.
     *
     * @param string $filePath
     * @return static
     */
    public static function invalidFile(string $filePath): static
    {
        return new static("Invalid PDF file: {$filePath}", null, $filePath);
    }

    /**
     * Create an extraction failed exception.
     *
     * @param string $driver
     * @param string $filePath
     * @param string $reason
     * @return static
     */
    public static function extractionFailed(string $driver, string $filePath, string $reason): static
    {
        return new static(
            "PDF extraction failed using '{$driver}' driver for file '{$filePath}': {$reason}",
            $driver,
            $filePath
        );
    }

    /**
     * Create a timeout exception.
     *
     * @param string $driver
     * @param string $filePath
     * @param int $timeout
     * @return static
     */
    public static function timeout(string $driver, string $filePath, int $timeout): static
    {
        return new static(
            "PDF extraction timed out after {$timeout} seconds using '{$driver}' driver for file '{$filePath}'",
            $driver,
            $filePath
        );
    }
} 