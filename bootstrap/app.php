<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Actions\CrawlLinks;
use App\Actions\CrawlAi;
use App\Actions\CrawlWebsite;
use App\Actions\ExtractPdfText;
use App\Actions\DisplayPdfContent;
use App\Actions\ListPdfDocuments;
use App\Actions\CheckPdfStatus;
use App\Actions\ProcessPdfDocument;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CrawlLinks::class,
        CrawlAi::class,
        CrawlWebsite::class,
        ExtractPdfText::class,
        DisplayPdfContent::class,
        ListPdfDocuments::class,
        CheckPdfStatus::class,
        ProcessPdfDocument::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
