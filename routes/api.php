<?php

// ============================================================
// ROUTES/API.PHP
// ============================================================
// File ini adalah "peta jalan" API kamu.
// Setiap request HTTP yang masuk ke server akan dicek dulu di
// sini — apakah URL-nya cocok dengan route yang terdaftar?
// Kalau cocok, Laravel teruskan ke Controller yang sesuai.
// Kalau tidak cocok, Laravel otomatis balas 404 Not Found.
// ============================================================

// "use" di PHP artinya: import class dari namespace lain supaya
// bisa dipakai di file ini tanpa nulis nama lengkapnya.
// Contoh: tanpa use, kamu harus tulis:
//   \App\Http\Controllers\AuthController::class
// Dengan use, cukup:
//   AuthController::class
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicOrderController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// Route::prefix('v1') artinya semua route di dalam group ini
// otomatis diawali dengan '/v1/'.
// Contoh: '/welcome' di dalam group ini menjadi '/api/v1/welcome'.
// Kenapa pakai versi? Supaya kalau API berubah di masa depan,
// kamu bisa buat '/v2/' tanpa merusak client yang masih pakai v1.
Route::prefix('v1')
    // ->middleware([...]) artinya: sebelum request masuk ke Controller,
    // Laravel jalankan dulu semua middleware yang ada di array ini secara urut.
    // Middleware = "satpam" yang memeriksa atau memodifikasi request.
    ->middleware([
        // EncryptCookies: enkripsi semua cookie yang dikirim ke client.
        // Ini keamanan bawaan Laravel supaya cookie tidak bisa dimanipulasi.
        EncryptCookies::class,

        // AddQueuedCookiesToResponse: tambahkan cookie yang sudah di-queue
        // ke dalam response sebelum dikirim balik ke client.
        AddQueuedCookiesToResponse::class,

        // StartSession: aktifkan session untuk request ini.
        // Session = cara server "ingat" siapa kamu di antara request.
        // Dibutuhkan supaya Auth::user() bisa tahu siapa yang sedang login.
        StartSession::class,

        // SubstituteBindings: aktifkan Route Model Binding.
        // Ini yang bikin parameter seperti {customer} di URL otomatis
        // diubah jadi object Customer dari database.
        // Contoh: GET /customers/5 → $customer sudah berisi data Customer id=5.
        SubstituteBindings::class,
    ])
    // ->group(function() {...}) artinya: semua route di dalam closure ini
    // mewarisi prefix dan middleware yang sudah didefinisikan di atas.
    // Closure = function tanpa nama yang langsung dieksekusi.
    ->group(function () {

        // Route sederhana tanpa controller — langsung balas JSON.
        // Cocok untuk endpoint health-check atau testing koneksi.
        Route::get('/welcome', function () {
            return response()->json([
                'message' => 'Welcome to Laundry API v1',
            ]);
        });

        // Route testing sementara — bisa dihapus kalau sudah tidak dibutuhkan.
        Route::post('/public/orders/test', function () {
            return response()->json([
                'message' => 'Route public orders kena',
            ]);
        });

        // Route publik — tidak butuh login.
        // Siapa saja bisa akses endpoint ini untuk membuat order baru.
        // [PublicOrderController::class, 'store'] artinya:
        // panggil method 'store' yang ada di class PublicOrderController.
        Route::post('/public/orders', [PublicOrderController::class, 'store']);

        // ============================================================
        // SEO Endpoints — publik, tidak butuh login.
        // ============================================================
        // GET /api/v1/seo/meta/{page}
        //   Contoh: /api/v1/seo/meta/home
        //           /api/v1/seo/meta/services
        //           /api/v1/seo/meta/services.5
        // GET /api/v1/seo/schema/{page}
        //   Contoh: /api/v1/seo/schema/home
        //           /api/v1/seo/schema/services
        //           /api/v1/seo/schema/services.5
        //
        // {page} boleh berisi titik (misal: services.5), jadi pakai
        // ->where() untuk izinkan format itu di parameter URL.
        Route::prefix('seo')->group(function () {
            Route::get('/meta/{page}', [SeoController::class, 'meta'])
                ->where('page', '[a-zA-Z0-9._-]+');

            Route::get('/schema/{page}', [SeoController::class, 'schema'])
                ->where('page', '[a-zA-Z0-9._-]+');
        });

        // Route auth — tidak butuh login (karena ini proses login/registernya).
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // ->middleware('auth') artinya endpoint logout hanya bisa diakses
        // kalau sudah login. Kalau belum login dan coba akses, dapat 401.
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

        // Route internal — hanya bisa diakses kalau sudah login DAN role-nya admin.
        // 'auth'  = cek apakah sudah login (dari SessionGuard Laravel)
        // 'admin' = cek apakah role user = 'admin' (dari AdminMiddleware kita)
        // Kedua middleware dijalankan urut: auth dulu, baru admin.
        Route::middleware(['auth', 'admin'])->group(function () {

            // Route::apiResource() satu baris ini otomatis membuat 5 route sekaligus:
            // GET    /customers          → CustomerController@index   (list semua)
            // POST   /customers          → CustomerController@store   (buat baru)
            // GET    /customers/{id}     → CustomerController@show    (detail satu)
            // PUT    /customers/{id}     → CustomerController@update  (update)
            // DELETE /customers/{id}     → CustomerController@destroy (hapus)
            // Cara lain: bisa ditulis manual satu per satu, tapi apiResource jauh lebih ringkas.
            Route::apiResource('customers', CustomerController::class);
            Route::apiResource('services', ServiceController::class);
            Route::apiResource('orders', OrderController::class);
            Route::apiResource('order-items', OrderItemController::class);

            // Nested resource — payments hidup di bawah orders.
            // URL pattern: /api/v1/orders/{order}/payments
            //
            // KENAPA nested (bukan flat /payments)?  
            // Karena payment tidak bisa berdiri sendiri — selalu milik satu order.
            // Dengan nested route, Laravel otomatis inject $order dari URL.
            // Ini juga mencegah akses payment milik order lain.
            //
            // Route yang dibuat otomatis:
            // GET    /orders/{order}/payments              → index   (list semua payment order ini)
            // POST   /orders/{order}/payments              → store   (catat pembayaran baru)
            // GET    /orders/{order}/payments/{payment}    → show    (detail satu payment)
            // DELETE /orders/{order}/payments/{payment}    → destroy (hapus payment)
            // PUT tidak ada karena payment biasanya tidak di-edit, hanya hapus + buat baru.
            Route::apiResource('orders.payments', PaymentController::class)
                ->only(['index', 'store', 'show', 'destroy']);

            // Nested resource untuk status history — hidup di bawah orders.
            // URL pattern: /api/v1/orders/{order}/statuses
            //
            // Route yang dibuat:
            // GET  /orders/{order}/statuses              → index (timeline semua status)
            // POST /orders/{order}/statuses              → store (ubah status + catat history)
            // GET  /orders/{order}/statuses/{status}     → show  (detail satu record history)
            //
            // TIDAK ada PUT dan DELETE karena:
            // - History status adalah audit trail yang tidak boleh diubah atau dihapus.
            // - Kalau salah, buat record baru yang benar — jangan hapus yang lama.
            Route::apiResource('orders.statuses', OrderStatusController::class)
                ->only(['index', 'store', 'show']);
        });
    });
