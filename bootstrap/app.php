<?php

error_reporting(E_ALL & ~E_DEPRECATED);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\ReconcileStalePolicies::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // API and web middleware configurations
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Domain exception mappings
    })->create();
