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
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicOrderController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Route::prefix('v1') artinya semua route di dalam group ini
// otomatis diawali dengan '/v1/'.
// Contoh: '/welcome' di dalam group ini menjadi '/api/v1/welcome'.
// Kenapa pakai versi? Supaya kalau API berubah di masa depan,
// kamu bisa buat '/v2/' tanpa merusak client yang masih pakai v1.
Route::prefix('v1')
    // Dengan Sanctum, kita tidak butuh session middleware lagi.
    // Sanctum membaca token dari header Authorization: Bearer TOKEN
    // secara otomatis tanpa perlu session atau cookie.
    //
    // SubstituteBindings tetap dibutuhkan untuk Route Model Binding.
    ->middleware([
        // SubstituteBindings: aktifkan Route Model Binding.
        // Ini yang bikin parameter seperti {order} di URL otomatis
        // diubah jadi object Order dari database.
        // Contoh: GET /orders/5 → $order sudah berisi data Order id=5.
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
        // throttle:public-order = maksimal 10 order/menit/IP
        Route::post('/public/orders', [PublicOrderController::class, 'store'])->middleware('throttle:public-order');
        Route::get('/public/services', [PublicOrderController::class, 'services']);
        Route::get('/public/orders/{order_number}', [PublicOrderController::class, 'track']);

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

        // Route auth — tidak butuh login.
        // throttle:register = pakai rate limit 'register' dari AppServiceProvider (3x/jam/IP)
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
        // throttle:login = pakai rate limit 'login' dari AppServiceProvider (5x/menit/IP)
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

        // Route yang butuh login (token Sanctum).
        // 'auth:sanctum' = middleware Sanctum yang cek token di header Authorization.
        // Berbeda dari 'auth' biasa yang cek session.
        // Cara kirim token: tambahkan header di setiap request:
        //   Authorization: Bearer 1|abcdefghij...
        //   Accept: application/json
        // Urutan middleware penting:
        // 1. auth:sanctum  → pastikan token valid
        // 2. token.activity → baru cek apakah token masih aktif (sliding expiry)
        // Kalau urutannya dibalik, token.activity tidak bisa akses $request->user()
        // karena Sanctum belum memproses token-nya.
        Route::middleware(['auth:sanctum', 'token.activity'])->group(function () {
            // Logout dan cek user yang sedang login.
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Route internal — hanya bisa diakses kalau sudah login DAN role-nya admin.
        // 'auth:sanctum'   = cek token Sanctum valid
        // 'token.activity' = cek token tidak idle > IDLE_TIMEOUT menit
        // 'admin'          = cek role = 'admin'
        // throttle:api = rate limit untuk semua endpoint admin (120 req/menit/user)
        Route::middleware(['auth:sanctum', 'token.activity', 'admin', 'throttle:api'])->group(function () {

            // Route::apiResource() satu baris ini otomatis membuat 5 route sekaligus:
            // GET    /customers          → CustomerController@index   (list semua)
            // POST   /customers          → CustomerController@store   (buat baru)
            // GET    /customers/{id}     → CustomerController@show    (detail satu)
            // PUT    /customers/{id}     → CustomerController@update  (update)
            // DELETE /customers/{id}     → CustomerController@destroy (hapus)
            // Cara lain: bisa ditulis manual satu per satu, tapi apiResource jauh lebih ringkas.
            Route::apiResource('customers', CustomerController::class);
            // Riwayat order milik satu customer: GET /customers/{customer}/orders
            Route::get('customers/{customer}/orders', [CustomerController::class, 'orders']);

            Route::apiResource('services', ServiceController::class);
            Route::apiResource('orders', OrderController::class);
            Route::apiResource('order-items', OrderItemController::class);

            // Delivery nested di bawah orders.
            // GET/POST /orders/{order}/deliveries
            // GET/PUT/DELETE /orders/{order}/deliveries/{delivery}
            Route::apiResource('orders.deliveries', DeliveryController::class);

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

            // ============================================================
            // USER MANAGEMENT
            // ============================================================
            Route::apiResource('users', UserController::class);
            // Toggle aktif/nonaktif user: PATCH /users/{user}/toggle-active
            Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);

            // ============================================================
            // EXPORT
            // ============================================================
            Route::prefix('export')->group(function () {
                // GET /export/orders/csv?date_from=2026-03-01&date_to=2026-03-31
                Route::get('/orders/csv',      [ExportController::class, 'ordersCSV']);
                // GET /export/payments/csv?date_from=2026-03-01&date_to=2026-03-31
                Route::get('/payments/csv',    [ExportController::class, 'paymentsCSV']);
                // GET /export/revenue/print?date_from=2026-03-01&date_to=2026-03-31
                // Buka di browser → Ctrl+P → Save as PDF
                Route::get('/revenue/print',   [ExportController::class, 'revenueHTML']);
            });

            // ============================================================
            // REPORTING ENDPOINTS
            // ============================================================
            // Semua endpoint di bawah ini READ-ONLY (hanya GET).
            // Dikelompokkan di bawah prefix 'reports' supaya mudah dikenali.
            //
            // Daftar endpoint:
            // GET /reports/summary           → ringkasan dashboard (semua metrik)
            // GET /reports/orders/daily      → order & omzet hari ini
            // GET /reports/revenue           → omzet per periode (daily/weekly/monthly)
            // GET /reports/orders/pending    → order belum selesai
            // GET /reports/orders/unpaid     → order belum lunas
            // GET /reports/services/top      → layanan terlaris
            Route::prefix('reports')->group(function () {
                Route::get('/summary',        [ReportController::class, 'summary']);
                Route::get('/orders/daily',   [ReportController::class, 'daily']);
                Route::get('/revenue',        [ReportController::class, 'revenue']);
                Route::get('/orders/pending', [ReportController::class, 'pendingOrders']);
                Route::get('/orders/unpaid',  [ReportController::class, 'unpaidOrders']);
                Route::get('/services/top',   [ReportController::class, 'topServices']);
            });
        });
    });
