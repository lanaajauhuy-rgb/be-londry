<?php

// "namespace" mendefinisikan "alamat" class ini di dalam project.
// Analoginya seperti folder: class AuthController ada di folder App\Http\Controllers.
// Tanpa namespace, kalau ada dua class bernama sama di file berbeda, PHP bingung.
namespace App\Http\Controllers;

// Import class yang dibutuhkan. Kalau tidak di-import, PHP tidak tahu
// User itu class apa dan ada di mana.
use App\Models\User;
use Illuminate\Http\JsonResponse; // tipe return value — memastikan method selalu return JSON
use Illuminate\Http\Request;     // object yang berisi semua data dari HTTP request (body, header, dll)
use Illuminate\Support\Facades\Auth; // facade untuk autentikasi — login, logout, ambil user

// Controller adalah class yang menerima request dari Route dan memutuskan
// apa yang harus dilakukan: validasi data, proses logika, kembalikan response.
// AuthController khusus menangani proses autentikasi (register, login, logout).
class AuthController extends Controller
{
    // Method register() dipanggil saat ada POST /api/v1/register.
    // Parameter $request berisi semua data yang dikirim client (body JSON, header, dll).
    // Return type ": JsonResponse" artinya method ini WAJIB mengembalikan response JSON.
    public function register(Request $request): JsonResponse
    {
        // $request->validate() melakukan dua hal sekaligus:
        // 1. Periksa apakah data yang dikirim sesuai aturan.
        // 2. Kalau tidak sesuai, OTOMATIS balas 422 Unprocessable Entity dengan pesan error.
        //    Kamu tidak perlu tulis if/else untuk ini — Laravel yang urus.
        // Kalau lolos validasi, $validated berisi array data yang sudah bersih dan aman.
        $validated = $request->validate([
            // Format: 'nama_field' => ['aturan1', 'aturan2', ...]
            'name'     => ['required', 'string', 'max:255'],
            // 'email' = cek format email valid.
            // 'unique:users,email' = cek ke tabel users, pastikan email belum terdaftar.
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            // 'confirmed' = Laravel akan cek apakah ada field 'password_confirmation'
            // yang nilainya sama dengan 'password'. Kalau tidak ada atau beda → error.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // 'nullable' = field ini boleh tidak dikirim atau dikirim dengan nilai null.
            'phone'    => ['nullable', 'string', 'max:20'],
        ]);

        // User::create() menyimpan data baru ke tabel users.
        // Hanya field yang ada di $fillable (di Model User) yang bisa disimpan.
        // Ini proteksi "mass assignment" — supaya client tidak bisa isi field sembarangan.
        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            // Password tidak perlu di-hash manual. Model User sudah punya casts 'hashed',
            // jadi Laravel otomatis hash password saat disimpan ke database.
            'password'  => $validated['password'],
            // Operator '??' = null coalescing. Artinya:
            // "ambil $validated['phone'] kalau ada, kalau tidak ada pakai null".
            // Dibutuhkan karena field phone nullable, mungkin tidak dikirim client.
            'phone'     => $validated['phone'] ?? null,
            // Semua user yang register lewat endpoint ini langsung jadi admin.
            // Di aplikasi nyata, ini biasanya lebih ketat (misal: butuh kode undangan).
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // response()->json() membuat HTTP response dengan body JSON.
        // Angka 201 = HTTP status "Created" — artinya data baru berhasil dibuat.
        // Kalau tidak ditulis angkanya, default adalah 200 (OK).
        return response()->json([
            'message' => 'Register berhasil',
            'data'    => $user, // Laravel otomatis convert object $user ke JSON
        ], 201);
    }

    // Method login() dipanggil saat ada POST /api/v1/login.
    public function login(Request $request): JsonResponse
    {
        // Validasi hanya butuh email dan password untuk login.
        // Tidak perlu cek unique karena kita hanya mau verifikasi, bukan menyimpan.
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Auth::attempt() melakukan dua hal:
        // 1. Cari user di database berdasarkan email.
        // 2. Cocokkan password yang dikirim dengan hash di database.
        // Kalau cocok: login user ke session, return true.
        // Kalau tidak cocok: return false, session tidak dibuat.
        if (! Auth::attempt($credentials)) {
            // 401 = Unauthorized. Artinya: identitas tidak dikenali.
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        // @var adalah PHPDoc comment — bukan kode yang dieksekusi.
        // Fungsinya hanya memberitahu IDE (WebStorm) bahwa $user adalah object User.
        // Tanpa ini, IDE tidak tahu tipe $user dan tidak bisa autocomplete propertinya.
        /** @var \App\Models\User $user */
        $user = Auth::user(); // ambil object user yang sedang login dari session

        // Cek apakah akun aktif. Bisa saja user ada di DB tapi dinonaktifkan admin.
        if (! $user->is_active) {
            // Logout dulu supaya session yang baru dibuat Auth::attempt() dibersihkan.
            Auth::logout();

            // 403 = Forbidden. Artinya: identitas dikenali tapi tidak punya akses.
            // Beda dengan 401: 401 = "siapa kamu?", 403 = "aku tahu kamu, tapi tidak boleh masuk".
            return response()->json([
                'message' => 'Akun tidak aktif',
            ], 403);
        }

        // Simpan waktu login terakhir. Berguna untuk audit log atau fitur "last seen".
        // now() = helper Laravel yang mengembalikan waktu sekarang (Carbon object).
        $user->update([
            'last_login_at' => now(),
        ]);

        // 200 OK (default, tidak perlu ditulis angkanya).
        return response()->json([
            'message' => 'Login berhasil',
            'data'    => $user,
        ]);
    }

    // Method logout() dipanggil saat ada POST /api/v1/logout.
    // Route ini dilindungi middleware 'auth', jadi hanya user yang sudah login bisa akses.
    public function logout(Request $request): JsonResponse
    {
        // Hapus data autentikasi dari session — user dianggap sudah tidak login.
        Auth::logout();

        // invalidate() = hapus seluruh data session yang ada.
        // Ini penting supaya session lama tidak bisa dipakai lagi.
        $request->session()->invalidate();

        // regenerateToken() = buat CSRF token baru.
        // CSRF token = proteksi dari serangan Cross-Site Request Forgery.
        // Setelah logout, token lama tidak valid lagi.
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }
}
