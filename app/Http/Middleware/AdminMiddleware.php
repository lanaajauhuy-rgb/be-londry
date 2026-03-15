<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ============================================================
// MIDDLEWARE = "SATPAM" REQUEST
// ============================================================
// Middleware adalah kode yang dijalankan SEBELUM request sampai
// ke Controller. Dipakai untuk:
// - Cek autentikasi (sudah login?)
// - Cek otorisasi (punya akses?)
// - Modifikasi request atau response
// - Logging, rate limiting, dll
//
// Alur request dengan middleware:
// Client → Middleware → Controller → Middleware → Client
//                        (masuk)              (keluar)
// ============================================================
class AdminMiddleware
{
    // Method handle() adalah inti dari middleware.
    // Dipanggil otomatis oleh Laravel untuk setiap request yang melewati middleware ini.
    //
    // Parameter:
    // $request = object berisi semua data HTTP request yang masuk
    // $next    = Closure (function) yang meneruskan request ke middleware/controller berikutnya
    //
    // Return type Response = bisa berupa JsonResponse, RedirectResponse, dll.
    public function handle(Request $request, Closure $next): Response
    {
        // $request->user() mengembalikan object User yang sedang login.
        // Kalau belum login / session tidak valid, mengembalikan null.
        // Tanda '!' di depan artinya negasi: "kalau TIDAK ada user yang login".
        if (! $request->user()) {
            // Langsung balas 401 — request tidak perlu diteruskan ke Controller.
            // Ini "early return" — teknik keluar lebih awal dari function
            // supaya kode tidak perlu nested if-else yang dalam.
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Sampai sini artinya user sudah login.
        // Sekarang cek apakah role-nya admin.
        // '!==' = perbandingan ketat (strict): cek nilai DAN tipe datanya.
        // Berbeda dengan '!=' yang hanya cek nilai saja.
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden. Hanya admin yang boleh mengakses resource ini.',
            ], 403);
        }

        // Sampai sini artinya user sudah login DAN role-nya admin.
        // $next($request) artinya: "lanjutkan request ini ke middleware/controller berikutnya".
        // Kalau $next tidak dipanggil, request akan berhenti di sini dan Controller tidak pernah dijalankan.
        return $next($request);
    }
}
