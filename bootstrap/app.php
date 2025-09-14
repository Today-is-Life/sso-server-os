<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register rate limiting middleware
        $middleware->alias([
            'rate.limit' => \App\Http\Middleware\RateLimitingMiddleware::class,
        ]);

        // Apply rate limiting to API routes
        $middleware->api([
            'rate.limit:api'
        ]);

        // Temporarily remove CSRF protection for testing
        $middleware->web(remove: [
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // Remove the problematic middleware groups for now
        // We'll apply rate limiting directly to routes instead
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
