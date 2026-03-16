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
    ->withMiddleware(function (Middleware $middleware): void {
        // CATATAN: statefulApi() TIDAK dipakai di sini.
        //
        // statefulApi() menambahkan EnsureFrontendRequestsAreStateful middleware
        // yang sengaja menginisialisasi session di setiap request = overhead ~500ms.
        //
        // Itu hanya dibutuhkan kalau kamu pakai Sanctum untuk SPA (Single Page App)
        // yang login lewat cookie. Karena kita pakai PURE TOKEN (Authorization: Bearer),
        // middleware itu tidak dibutuhkan dan justru memperlambat semua request.
        //
        // Sanctum tetap bekerja dengan auth:sanctum guard tanpa statefulApi().
        $middleware->alias([
            'admin'          => AdminMiddleware::class,
            // 'token.activity' = cek apakah token masih aktif (sliding expiry).
            // Dipasang di route group yang butuh login supaya user
            // otomatis di-logout setelah idle IDLE_TIMEOUT menit.
            'token.activity' => CheckTokenActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
