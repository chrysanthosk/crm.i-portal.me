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

        /**
         * Register route middleware aliases (Laravel 11/12 style).
         * This FIXES: "Target class [permission] does not exist."
         */
        $middleware->alias([
            'theme'      => \App\Http\Middleware\EnsureTheme::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);

        // If you want theme to run for *all* web routes automatically,
        // uncomment this (then remove 'theme' from route groups if you want):
        // $middleware->web(append: [
        //     \App\Http\Middleware\EnsureTheme::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
