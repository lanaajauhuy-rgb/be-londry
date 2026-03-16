<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// DeliveryController menangani pickup dan delivery untuk order.
//
// Satu order bisa punya dua delivery record:
// 1. type='pickup'   → kurir jemput laundry dari alamat customer
// 2. type='delivery' → kurir antar laundry yang sudah selesai ke customer
//
// Nested di bawah orders: /api/v1/orders/{order}/deliveries
class DeliveryController extends Controller
{
    // index() — GET /api/v1/orders/{order}/deliveries
    // List semua pickup/delivery untuk order ini.
    public function index(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->deliveries()->with('courier:id,name,phone')->get(),
        ]);
    }

    // store() — POST /api/v1/orders/{order}/deliveries
    // Buat jadwal pickup atau delivery baru.
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            // type harus 'pickup' atau 'delivery' — tidak ada nilai lain.
            'type'             => ['required', Rule::in(['pickup', 'delivery'])],
            'address'          => ['required', 'string'],
            'scheduled_at'     => ['nullable', 'date'],
            // courier_user_id opsional — bisa di-assign belakangan.
            'courier_user_id'  => ['nullable', 'exists:users,id'],
            'notes'            => ['nullable', 'string'],
        ]);

        $delivery = Delivery::create([
            'order_id'        => $order->id,
            'type'            => $validated['type'],
            'address'         => $validated['address'],
            'scheduled_at'    => $validated['scheduled_at'] ?? null,
            'completed_at'    => null,
            'courier_user_id' => $validated['courier_user_id'] ?? null,
            // Status awal selalu 'pending' saat baru dibuat.
            'status'          => 'pending',
            'notes'           => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => ucfirst($validated['type']) . ' berhasil dijadwalkan',
            'data'    => $delivery,
        ], 201);
    }

    // show() — GET /api/v1/orders/{order}/deliveries/{delivery}
    public function show(Order $order, Delivery $delivery): JsonResponse
    {
        $delivery->load('courier:id,name,phone');

        return response()->json([
            'data' => $delivery,
        ]);
    }

    // update() — PUT /api/v1/orders/{order}/deliveries/{delivery}
    // Update detail delivery: assign kurir, ubah jadwal, ubah status.
    public function update(Request $request, Order $order, Delivery $delivery): JsonResponse
    {
        $validated = $request->validate([
            'address'         => ['sometimes', 'string'],
            'scheduled_at'    => ['sometimes', 'nullable', 'date'],
            'courier_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            // Status delivery mengikuti alur: pending → on_the_way → done / cancelled
            'status'          => ['sometimes', Rule::in(['pending', 'on_the_way', 'done', 'cancelled'])],
            'notes'           => ['nullable', 'string'],
        ]);

        // Kalau status berubah jadi 'done', isi completed_at otomatis.
        if (isset($validated['status']) && $validated['status'] === 'done') {
            $validated['completed_at'] = now();
        }

        // Kalau status berubah jadi 'cancelled', kosongkan completed_at.
        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $validated['completed_at'] = null;
        }

        $delivery->update($validated);

        return response()->json([
            'message' => 'Delivery berhasil diupdate',
            'data'    => $delivery->fresh(['courier:id,name,phone']),
        ]);
    }

    // destroy() — DELETE /api/v1/orders/{order}/deliveries/{delivery}
    // Hapus jadwal delivery yang belum selesai.
    public function destroy(Order $order, Delivery $delivery): JsonResponse
    {
        // Tidak bisa hapus delivery yang sudah selesai — sudah jadi histori.
        if ($delivery->status === 'done') {
            return response()->json([
                'message' => 'Delivery yang sudah selesai tidak bisa dihapus.',
            ], 422);
        }

        $delivery->delete();

        return response()->json([
            'message' => 'Jadwal delivery berhasil dihapus',
        ]);
    }
}
