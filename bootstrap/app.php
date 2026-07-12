<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckTokenActivity;
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
    ->withEvents(discover: [])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin'          => AdminMiddleware::class,
            'token.activity' => CheckTokenActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
