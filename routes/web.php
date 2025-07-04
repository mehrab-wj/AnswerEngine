<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Services\PdfTextExtractor\PdfTextExtractor;
use App\Actions\ConvertTextToMarkdown;
use App\Http\Controllers\DashboardController;

Route::get('/test', function () {
    $extractor = new PdfTextExtractor();
    // Extract text and metadata
    $text = $extractor->extract(public_path('interview-book.pdf'));
    $metadata = $extractor->extractMetadata(public_path('interview-book.pdf'));
    $markdown = ConvertTextToMarkdown::make()->handle($text);

    echo "<pre> " . $markdown . "</pre>";
});

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
