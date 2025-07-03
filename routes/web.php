<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Actions\ChunkMarkdown;
use App\Actions\CrawlAi;

Route::get('/test', function () {
    $response = CrawlAi::make()->handle('https://shreycation.substack.com/');
    $markdown = $response->rawMarkdown();
    $chunks = ChunkMarkdown::make()->handle($markdown);
    dd($chunks);
});

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
