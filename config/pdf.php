<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default PDF Text Extraction Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default driver used for extracting text from
    | PDF files. You may set this to any of the drivers defined in the
    | "drivers" array below.
    |
    | Supported drivers: "smalot", "spatie", "null"
    |
    */
    'default_driver' => env('PDF_EXTRACTION_DRIVER', 'smalot'),

    /*
    |--------------------------------------------------------------------------
    | Fallback PDF Text Extraction Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the fallback driver used when the primary driver
    | fails to extract text from a PDF file. Set to null to disable fallback.
    |
    */
    'fallback_driver' => env('PDF_EXTRACTION_FALLBACK_DRIVER', 'spatie'),

    /*
    |--------------------------------------------------------------------------
    | Extraction Timeout
    |--------------------------------------------------------------------------
    |
    | This option controls the maximum time (in seconds) allowed for PDF
    | text extraction operations. Large files may require longer timeouts.
    |
    */
    'timeout' => env('PDF_EXTRACTION_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | This option controls the maximum file size (in MB) that will be
    | processed for text extraction. Files larger than this will be rejected.
    |
    */
    'max_file_size' => env('PDF_EXTRACTION_MAX_FILE_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Driver Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the drivers for PDF text extraction. Each driver
    | has its own configuration options and requirements.
    |
    */
    'drivers' => [
        'smalot' => [
            'class' => \App\Services\PdfTextExtractor\Drivers\SmalotPdfDriver::class,
            'options' => [
                'ignore_errors' => true,
                'decode_unicode' => true,
            ],
        ],

        'spatie' => [
            'class' => \App\Services\PdfTextExtractor\Drivers\SpatiePdfDriver::class,
            'binary_path' => env('PDF_SPATIE_BINARY_PATH', '/usr/bin/pdftotext'),
            'options' => env('PDF_SPATIE_OPTIONS', '-layout -enc UTF-8'),
        ],

        'null' => [
            'class' => \App\Services\PdfTextExtractor\Drivers\NullPdfDriver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Conversion Options
    |--------------------------------------------------------------------------
    |
    | These options control how extracted text is converted to Markdown format.
    |
    */
    'markdown' => [
        'preserve_formatting' => true,
        'detect_headings' => true,
        'detect_lists' => true,
        'minimum_heading_gap' => 2, // Lines between potential headings
        'heading_patterns' => [
            '/^[A-Z][A-Z\s]{5,}$/', // All caps text
            '/^\d+\.?\s+[A-Z]/', // Numbered sections
            '/^[IVX]+\.?\s+[A-Z]/', // Roman numerals
        ],
    ],
]; 