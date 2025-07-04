<?php

namespace App\Services\PdfTextExtractor;

use App\Services\PdfTextExtractor\Contracts\PdfDriverInterface;
use App\Services\PdfTextExtractor\Exceptions\PdfExtractionException;
use Illuminate\Support\Facades\Log;

class PdfTextExtractor
{
    /**
     * The driver instances.
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * The current driver name.
     *
     * @var string|null
     */
    protected ?string $currentDriver = null;

    /**
     * Create a new PDF text extractor instance.
     *
     * @param string|null $driver
     */
    public function __construct(?string $driver = null)
    {
        $this->currentDriver = $driver ?: config('pdf.default_driver');
    }

    /**
     * Extract text from a PDF file.
     *
     * @param string $filePath
     * @param string|null $driver
     * @return string
     * @throws PdfExtractionException
     */
    public function extract(string $filePath, ?string $driver = null): string
    {
        $driverName = $driver ?: $this->currentDriver;
        
        // Validate file
        $this->validateFile($filePath);

        try {
            $driver = $this->getDriver($driverName);
            
            Log::info("Extracting text from PDF", [
                'file' => $filePath,
                'driver' => $driverName,
                'size' => filesize($filePath)
            ]);

            $text = $this->executeWithTimeout(
                fn() => $driver->extract($filePath),
                config('pdf.timeout', 60)
            );

            Log::info("Successfully extracted text from PDF", [
                'file' => $filePath,
                'driver' => $driverName,
                'length' => strlen($text)
            ]);

            return $text;

        } catch (PdfExtractionException $e) {
            Log::error("PDF extraction failed", [
                'file' => $filePath,
                'driver' => $driverName,
                'error' => $e->getMessage()
            ]);

            // Try fallback driver if available
            $fallbackDriver = config('pdf.fallback_driver');
            if ($fallbackDriver && $fallbackDriver !== $driverName) {
                Log::info("Attempting fallback driver", [
                    'file' => $filePath,
                    'fallback_driver' => $fallbackDriver
                ]);

                try {
                    return $this->extract($filePath, $fallbackDriver);
                } catch (PdfExtractionException $fallbackException) {
                    Log::error("Fallback driver also failed", [
                        'file' => $filePath,
                        'fallback_driver' => $fallbackDriver,
                        'error' => $fallbackException->getMessage()
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Extract metadata from a PDF file.
     *
     * @param string $filePath
     * @param string|null $driver
     * @return array
     * @throws PdfExtractionException
     */
    public function extractMetadata(string $filePath, ?string $driver = null): array
    {
        $driverName = $driver ?: $this->currentDriver;
        
        // Validate file
        $this->validateFile($filePath);

        try {
            $driver = $this->getDriver($driverName);
            
            return $this->executeWithTimeout(
                fn() => $driver->extractMetadata($filePath),
                config('pdf.timeout', 60)
            );

        } catch (PdfExtractionException $e) {
            // Try fallback driver if available
            $fallbackDriver = config('pdf.fallback_driver');
            if ($fallbackDriver && $fallbackDriver !== $driverName) {
                try {
                    return $this->extractMetadata($filePath, $fallbackDriver);
                } catch (PdfExtractionException $fallbackException) {
                    // Log and ignore fallback failure for metadata
                }
            }

            throw $e;
        }
    }

    /**
     * Get the specified driver.
     *
     * @param string $name
     * @return PdfDriverInterface
     * @throws PdfExtractionException
     */
    public function getDriver(string $name): PdfDriverInterface
    {
        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a driver instance.
     *
     * @param string $name
     * @return PdfDriverInterface
     * @throws PdfExtractionException
     */
    protected function createDriver(string $name): PdfDriverInterface
    {
        $config = config("pdf.drivers.{$name}");

        if (!$config) {
            throw PdfExtractionException::driverNotFound($name);
        }

        $class = $config['class'];

        if (!class_exists($class)) {
            throw PdfExtractionException::driverNotFound($name);
        }

        $driver = new $class();

        if (!$driver instanceof PdfDriverInterface) {
            throw new PdfExtractionException("Driver {$name} must implement PdfDriverInterface");
        }

        // Set driver configuration
        $driver->setConfiguration($config);

        return $driver;
    }

    /**
     * Validate the PDF file.
     *
     * @param string $filePath
     * @throws PdfExtractionException
     */
    protected function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw PdfExtractionException::fileNotFound($filePath);
        }

        if (!is_readable($filePath)) {
            throw PdfExtractionException::invalidFile($filePath);
        }

        // Check file size
        $maxSize = config('pdf.max_file_size', 50) * 1024 * 1024; // Convert MB to bytes
        $fileSize = filesize($filePath);

        if ($fileSize > $maxSize) {
            throw PdfExtractionException::fileTooLarge(
                $filePath,
                round($fileSize / 1024 / 1024, 2),
                config('pdf.max_file_size', 50)
            );
        }

        // Check if it's a PDF file
        $mimeType = mime_content_type($filePath);
        if ($mimeType !== 'application/pdf') {
            throw PdfExtractionException::invalidFile($filePath);
        }
    }

    /**
     * Execute a function with timeout.
     *
     * @param callable $callback
     * @param int $timeout
     * @return mixed
     * @throws PdfExtractionException
     */
    protected function executeWithTimeout(callable $callback, int $timeout)
    {
        $start = time();
        
        try {
            return $callback();
        } catch (\Exception $e) {
            $elapsed = time() - $start;
            
            if ($elapsed >= $timeout) {
                throw PdfExtractionException::timeout(
                    $this->currentDriver,
                    'unknown',
                    $timeout
                );
            }
            
            throw $e;
        }
    }

    /**
     * Get available drivers.
     *
     * @return array
     */
    public function getAvailableDrivers(): array
    {
        return array_keys(config('pdf.drivers', []));
    }

    /**
     * Check if a driver is available.
     *
     * @param string $name
     * @return bool
     */
    public function hasDriver(string $name): bool
    {
        return in_array($name, $this->getAvailableDrivers());
    }

    /**
     * Set the current driver.
     *
     * @param string $driver
     * @return $this
     */
    public function using(string $driver): self
    {
        $this->currentDriver = $driver;
        return $this;
    }

    /**
     * Get the current driver name.
     *
     * @return string|null
     */
    public function getCurrentDriver(): ?string
    {
        return $this->currentDriver;
    }
} 