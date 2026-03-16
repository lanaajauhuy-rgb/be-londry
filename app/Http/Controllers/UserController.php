<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// UserController menangani manajemen user (staff, operator, kurir).
//
// Endpoint ini HANYA untuk admin — mengelola akun staff yang bisa login ke sistem.
// Berbeda dari AuthController yang menangani login/logout user itu sendiri.
//
// Role yang tersedia:
//   admin    = akses penuh ke semua fitur
//   cashier  = bisa buat order, catat pembayaran
//   operator = bisa update status order
//   courier  = bisa update status delivery
class UserController extends Controller
{
    // index() — GET /api/v1/users
    // List semua staff dengan search dan filter role.
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => ['sometimes', 'string', 'max:100'],
            'role'     => ['sometimes', Rule::in(['admin', 'cashier', 'operator', 'courier'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->latest();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 15));
    }

    // store() — POST /api/v1/users
    // Buat akun staff baru. Berbeda dari /register yang untuk admin,
    // endpoint ini memungkinkan admin membuat akun dengan role apapun.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'cashier', 'operator', 'courier'])],
            'phone'    => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => $validated['password'], // Model otomatis hash
            'role'      => $validated['role'],
            'phone'     => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data'    => $user,
        ], 201);
    }

    // show() — GET /api/v1/users/{id}
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user,
        ]);
    }

    // update() — PUT /api/v1/users/{id}
    // Update data staff. Password opsional — kalau tidak dikirim, tidak berubah.
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            // Password opsional saat update — kalau tidak dikirim, tidak diubah.
            // 'sometimes' + 'nullable' = boleh tidak ada, boleh null, boleh string.
            'password'  => ['sometimes', 'nullable', 'string', 'min:8'],
            'role'      => ['required', Rule::in(['admin', 'cashier', 'operator', 'courier'])],
            'phone'     => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Hanya update password kalau dikirim dan tidak null.
        $updateData = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'phone'     => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = $validated['password']; // Model otomatis hash
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'User berhasil diupdate',
            'data'    => $user->fresh(),
        ]);
    }

    // toggleActive() — PATCH /api/v1/users/{id}/toggle-active
    // Aktifkan atau nonaktifkan akun staff — lebih aman dari hapus permanen.
    // User yang dinonaktifkan tidak bisa login tapi data tetap ada.
    public function toggleActive(User $user): JsonResponse
    {
        // Proteksi: admin tidak bisa nonaktifkan dirinya sendiri.
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Tidak bisa menonaktifkan akun sendiri.',
            ], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Akun {$user->name} berhasil {$status}.",
            'data'    => $user->fresh(),
        ]);
    }

    // destroy() — DELETE /api/v1/users/{id}
    // Hapus akun staff. Hanya boleh hapus kalau tidak ada data terkait.
    public function destroy(User $user): JsonResponse
    {
        // Proteksi: admin tidak bisa hapus dirinya sendiri.
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Tidak bisa menghapus akun sendiri.',
            ], 422);
        }

        // Proteksi: kalau user ini pernah menerima order, tidak bisa dihapus.
        // Hapus user = FK received_by_user_id di orders jadi NULL (nullOnDelete),
        // tapi lebih aman nonaktifkan daripada hapus supaya histori tetap ada.
        $hasOrders = \App\Models\Order::where('received_by_user_id', $user->id)->exists();
        if ($hasOrders) {
            return response()->json([
                'message' => 'User tidak bisa dihapus karena sudah memiliki histori order. '
                             . 'Gunakan nonaktifkan saja.',
            ], 422);
        }

        // Hapus semua token aktif user ini dulu sebelum hapus user-nya.
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus',
        ]);
    }
}
