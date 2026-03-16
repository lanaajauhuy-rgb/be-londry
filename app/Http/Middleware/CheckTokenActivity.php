<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ============================================================
// CheckTokenActivity — Middleware Sliding Expiry
// ============================================================
// Middleware ini mengimplementasikan "sliding expiry" untuk token Sanctum.
//
// SLIDING EXPIRY artinya:
// Token expire bukan dari waktu dibuat, tapi dari waktu TERAKHIR DIPAKAI.
// Selama user aktif request, token terus hidup.
// Kalau tidak ada request selama X menit → token dianggap expired.
//
// Analogi: pintu otomatis yang menutup setelah 5 menit tidak ada gerakan.
// Selama ada orang lewat, pintu tetap buka.
//
// CARA KERJA:
// 1. Cek apakah user sudah login (punya token)
// 2. Ambil kolom 'last_used_at' dari token yang dipakai
// 3. Hitung selisih waktu: sekarang - last_used_at
// 4. Kalau selisihnya > batas idle → hapus token → return 401
// 5. Kalau masih dalam batas → lanjutkan request
//    (Sanctum otomatis update last_used_at saat token dipakai)
// ============================================================
class CheckTokenActivity
{
    // IDLE_TIMEOUT = berapa menit boleh tidak aktif sebelum token expired.
    // Ubah nilai ini sesuai kebutuhan.
    // Contoh: 5 = 5 menit, 30 = 30 menit, 480 = 8 jam
    private const IDLE_TIMEOUT = 30; // menit

    public function handle(Request $request, Closure $next): Response
    {
        // $request->user() = ambil user dari token di header Authorization.
        // Kalau tidak ada token atau token tidak valid, ini return null.
        $user = $request->user();

        // Kalau tidak ada user (belum login / token tidak valid),
        // biarkan request lanjut. auth:sanctum guard yang akan handle 401-nya.
        // Kita tidak perlu duplicate logika auth di sini.
        if (! $user) {
            return $next($request);
        }

        // currentAccessToken() = ambil object token yang dipakai di request ini.
        // Object ini berisi data dari tabel personal_access_tokens:
        // id, tokenable_type, tokenable_id, name, abilities, last_used_at, expires_at
        $token = $user->currentAccessToken();

        // Kalau tidak ada token object (misal user diautentikasi lewat session,
        // bukan token), skip cek ini.
        if (! $token) {
            return $next($request);
        }

        // $token->last_used_at adalah Carbon object (atau null kalau belum pernah dipakai).
        // Carbon adalah library tanggal/waktu Laravel.
        // ->diffInMinutes(now()) = hitung selisih menit antara last_used_at dan sekarang.
        if ($token->last_used_at !== null) {
            $idleMinutes = $token->last_used_at->diffInMinutes(now());

            // Kalau sudah idle lebih dari batas yang ditentukan:
            if ($idleMinutes >= self::IDLE_TIMEOUT) {
                // Hapus token dari database supaya tidak bisa dipakai lagi.
                // Ini berbeda dari sekedar return 401 tanpa hapus —
                // kalau tidak dihapus, token masih ada di DB dan bisa dicoba lagi.
                $token->delete();

                // Return 401 dengan pesan yang informatif.
                // Client harus redirect user ke halaman login.
                return response()->json([
                    'message'      => 'Sesi kamu sudah berakhir karena tidak aktif selama '
                                      . self::IDLE_TIMEOUT . ' menit. Silakan login kembali.',
                    'reason'       => 'token_expired_idle',
                    // Kasih tahu client berapa menit idle timeout-nya
                    // supaya bisa tampilkan pesan yang tepat di UI.
                    'idle_timeout' => self::IDLE_TIMEOUT,
                ], 401);
            }
        }

        // Token masih aktif — lanjutkan request.
        // Sanctum otomatis mengupdate last_used_at saat token berhasil dipakai,
        // jadi kita tidak perlu update manual di sini.
        // Ini yang membuat efek "sliding" — setiap request sukses = timer reset.
        return $next($request);
    }
}
