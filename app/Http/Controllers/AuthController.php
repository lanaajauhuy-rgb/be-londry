<?php

// "namespace" mendefinisikan "alamat" class ini di dalam project.
// Analoginya seperti folder: class AuthController ada di folder App\Http\Controllers.
// Tanpa namespace, kalau ada dua class bernama sama di file berbeda, PHP bingung.
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// AuthController setelah migrasi ke Sanctum token-based authentication.
//
// PERBEDAAN dari sebelumnya (session-based):
//
// SEBELUM (session):                    SESUDAH (token Sanctum):
//   Auth::attempt() → buat session        Auth::attempt() → tidak dipakai
//   Auth::user()    → dari session        $request->user() → dari token di header
//   Auth::logout()  → hapus session       $user->currentAccessToken()->delete() → hapus token
//   Cookie di browser                     Header: Authorization: Bearer TOKEN
class AuthController extends Controller
{
    // register() — POST /api/v1/register
    // Daftarkan user baru dan langsung kembalikan token.
    // Setelah register, client sudah bisa langsung akses endpoint protected
    // tanpa perlu login lagi.
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            // 'confirmed' = cek ada field 'password_confirmation' yang nilainya sama.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone'    => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            // Model User punya cast 'hashed' untuk password,
            // jadi tidak perlu Hash::make() secara manual.
            'password'  => $validated['password'],
            'phone'     => $validated['phone'] ?? null,
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // Buat token untuk user yang baru register.
        // createToken('nama-token') = buat Personal Access Token baru.
        // Nama token berguna untuk identifikasi — misal 'web', 'mobile', 'api'.
        // Satu user bisa punya banyak token dengan nama berbeda.
        //
        // ->plainTextToken = ambil token dalam bentuk string yang bisa dikirim ke client.
        // Format: "ID|RANDOM_STRING" contoh: "3|AbCdEfGhIj..."
        // Token ini HANYA MUNCUL SEKALI — setelah ini tidak bisa diambil lagi dari DB
        // karena yang disimpan di DB adalah hash-nya, bukan plaintext-nya.
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Register berhasil',
            // Kirim token ke client — client wajib simpan ini
            // dan kirim sebagai header di setiap request selanjutnya:
            // Authorization: Bearer <token>
            'token'   => $token,
            'data'    => $user,
        ], 201);
    }

    // login() — POST /api/v1/login
    // Verifikasi kredensial dan kembalikan token.
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cari user berdasarkan email.
        // firstWhere() = shorthand untuk where(...)->first()
        $user = User::firstWhere('email', $credentials['email']);

        // Cek dua hal:
        // 1. User dengan email ini ada?
        // 2. Password yang dikirim cocok dengan hash di DB?
        //
        // Hash::check('password_polos', 'hash_di_db') = verifikasi password.
        // KENAPA tidak pakai Auth::attempt() lagi?
        // Auth::attempt() otomatis buat session — kita tidak butuh session lagi.
        // Dengan cara manual ini kita lebih eksplisit dan tidak ada side effect session.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun tidak aktif',
            ], 403);
        }

        // Hapus semua token lama milik user ini sebelum buat yang baru.
        // KENAPA? Supaya satu user hanya punya satu token aktif.
        // Ini mencegah token lama masih bisa dipakai setelah login ulang.
        // ALTERNATIF: biarkan token lama (berguna kalau satu user login di banyak device).
        $user->tokens()->delete();

        $user->update(['last_login_at' => now()]);

        // Buat token baru.
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'data'    => $user,
        ]);
    }

    // logout() — POST /api/v1/logout
    // Hapus token yang sedang dipakai sehingga tidak bisa digunakan lagi.
    public function logout(Request $request): JsonResponse
    {
        // $request->user() = ambil user dari token yang ada di header Authorization.
        // Sanctum otomatis parse header ini dan kembalikan object User.
        //
        // currentAccessToken() = ambil token yang dipakai di request ini.
        // ->delete() = hapus token ini dari tabel personal_access_tokens.
        // Setelah ini, token yang dikirim client tidak valid lagi.
        $request->user()->currentAccessToken()->delete();

        // Tidak perlu invalidate session atau regenerate CSRF token
        // karena kita tidak pakai session sama sekali.
        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    // me() — GET /api/v1/me
    // Kembalikan data user yang sedang login berdasarkan token.
    // Endpoint ini berguna untuk client cek siapa yang sedang login
    // tanpa perlu menyimpan data user secara lokal.
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user(),
        ]);
    }
}
