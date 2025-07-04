<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Services\PdfTextExtractor\PdfTextExtractor;
use App\Actions\ConvertTextToMarkdown;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcessDetailsController;

Route::get('/phpinfo', function () {
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
    ];
});

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/add-website', [DashboardController::class, 'addWebsite'])->name('dashboard.add-website');
    Route::post('dashboard/upload-pdf', [DashboardController::class, 'uploadPdf'])->name('dashboard.upload-pdf');
    Route::delete('dashboard/source', [DashboardController::class, 'deleteSource'])->name('dashboard.delete-source');
    
    // Process detail routes
    Route::get('process/website/{uuid}', [ProcessDetailsController::class, 'showWebsiteProcess'])->name('process.website');
    Route::get('process/pdf/{uuid}', [ProcessDetailsController::class, 'showPdfProcess'])->name('process.pdf');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
