<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderStatusNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Daftarkan Event → Listener mapping.
        // Saat OrderStatusChanged di-dispatch, SendOrderStatusNotification akan dijalankan.
        // Bisa daftarkan banyak listener untuk satu event (email, WA, push, dll).
        Event::listen(
            OrderStatusChanged::class,
            SendOrderStatusNotification::class,
        );

        // ============================================================
        // RATE LIMITING — Batasi jumlah request untuk mencegah abuse
        // ============================================================
        // RateLimiter::for('nama', ...) = definisikan aturan rate limit.
        // Nama ini yang kemudian dipasang di route pakai middleware 'throttle:nama'.
        //
        // Limit::perMinute(X) = maksimal X request per menit.
        // ->by($key) = rate limit dihitung per key (biasanya per IP atau per user).
        // ============================================================

        // Rate limit untuk endpoint LOGIN — paling ketat.
        // Maksimal 5 percobaan login per menit per IP.
        // Kalau lebih → 429 Too Many Requests.
        // Ini mencegah brute-force attack: orang tidak bisa coba ribuan password.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                // Dihitung per IP address client.
                // ->by() menerima string sebagai key unik untuk counter.
                ->by($request->ip())
                // Response kustom saat limit terlampaui.
                ->response(function () {
                    return response()->json([
                        'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.',
                        'reason'  => 'rate_limit_exceeded',
                    ], 429);
                });
        });

        // Rate limit untuk endpoint REGISTER.
        // Maksimal 3 registrasi per jam per IP.
        // Ini mencegah pembuatan akun massal (spam accounts).
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Terlalu banyak percobaan registrasi. Coba lagi dalam 1 jam.',
                        'reason'  => 'rate_limit_exceeded',
                    ], 429);
                });
        });

        // Rate limit untuk endpoint PUBLIC ORDER (pelanggan buat order tanpa login).
        // Maksimal 10 order per menit per IP.
        // Mencegah spam order dari bot atau pihak yang tidak bertanggung jawab.
        RateLimiter::for('public-order', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Terlalu banyak request. Silakan tunggu sebentar.',
                        'reason'  => 'rate_limit_exceeded',
                    ], 429);
                });
        });

        // Rate limit untuk endpoint API yang butuh login (semua endpoint admin).
        // Maksimal 120 request per menit per USER (bukan per IP).
        // Ini lebih longgar karena user admin yang sah perlu banyak akses.
        // Dihitung per user id supaya satu user tidak bisa flood server.
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                // Kalau sudah login: dihitung per user id
                ? Limit::perMinute(120)->by($request->user()->id)
                // Kalau belum login: dihitung per IP (fallback)
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}
