<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// OrderItemController menangani CRUD order_items lewat endpoint admin.
// Order item = satu baris layanan di dalam sebuah order.
// Contoh: Order #001 punya 2 items — "Cuci Kiloan 3kg" dan "Setrika 5 pcs".
class OrderItemController extends Controller
{
    // index() — GET /api/v1/order-items
    // Catatan: endpoint ini mengembalikan SEMUA item dari semua order.
    // Di aplikasi nyata, biasanya di-filter by order_id:
    // OrderItem::where('order_id', $orderId)->latest()->get()
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OrderItem::latest()->get(),
        ]);
    }

    // store() — POST /api/v1/order-items
    // Tambah item baru ke order yang sudah ada.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'     => ['required', 'exists:orders,id'],
            'service_id'   => ['required', 'exists:services,id'],
            // Nilai harus 'kiloan' atau 'per_item' — sesuai ENUM di tabel order_items.
            // Jangan kirim 'per_kg' atau 'flat' karena tidak ada di ENUM → error DB.
            // Mapping dari services.pricing_model ke order_items.service_type:
            //   per_kg   → kiloan
            //   per_item → per_item
            //   flat     → per_item
            'service_type' => ['required', Rule::in(['kiloan', 'per_item'])],
            'item_name'    => ['nullable', 'string', 'max:255'],
            // 'integer' = hanya bilangan bulat. Tidak bisa kirim qty = 1.5.
            // 'min:1' = minimal 1, tidak boleh 0 atau negatif.
            'qty'          => ['required', 'integer', 'min:1'],
            'weight_kg'    => ['nullable', 'numeric', 'min:0'],
            'unit_price'   => ['required', 'numeric', 'min:0'],
            'line_total'   => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
        ]);

        // KENAPA pakai empty() bukan array_key_exists()?
        // Setelah validate(), field nullable seperti weight_kg SELALU ada di $validated
        // sebagai key, nilainya null. Jadi array_key_exists() selalu return true.
        // empty() lebih tepat karena cek NILAI-nya: null, 0, '', false = empty.
        //
        // ALTERNATIF yang lebih eksplisit:
        // if ($validated['service_type'] === 'kiloan' && $validated['weight_kg'] === null)
        if ($validated['service_type'] === 'kiloan' && empty($validated['weight_kg'])) {
            return response()->json([
                'message' => 'weight_kg wajib diisi dan lebih dari 0 untuk service_type kiloan',
            ], 422);
        }

        $orderItem = OrderItem::create($validated);

        return response()->json([
            'message' => 'Order item berhasil dibuat',
            'data'    => $orderItem,
        ], 201);
    }

    // show() — GET /api/v1/order-items/{id}
    public function show(OrderItem $orderItem): JsonResponse
    {
        return response()->json([
            'data' => $orderItem,
        ]);
    }

    // update() — PUT /api/v1/order-items/{id}
    public function update(Request $request, OrderItem $orderItem): JsonResponse
    {
        $validated = $request->validate([
            'order_id'     => ['required', 'exists:orders,id'],
            'service_id'   => ['required', 'exists:services,id'],
            'service_type' => ['required', Rule::in(['kiloan', 'per_item'])],
            'item_name'    => ['nullable', 'string', 'max:255'],
            'qty'          => ['required', 'integer', 'min:1'],
            'weight_kg'    => ['nullable', 'numeric', 'min:0'],
            'unit_price'   => ['required', 'numeric', 'min:0'],
            'line_total'   => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
        ]);

        // Cek yang sama seperti store() — weight_kg wajib untuk service kiloan.
        if ($validated['service_type'] === 'kiloan' && empty($validated['weight_kg'])) {
            return response()->json([
                'message' => 'weight_kg wajib diisi dan lebih dari 0 untuk service_type kiloan',
            ], 422);
        }

        $orderItem->update($validated);

        return response()->json([
            'message' => 'Order item berhasil diupdate',
            'data'    => $orderItem,
        ]);
    }

    // destroy() — DELETE /api/v1/order-items/{id}
    public function destroy(OrderItem $orderItem): JsonResponse
    {
        $orderItem->delete();

        return response()->json([
            'message' => 'Order item berhasil dihapus',
        ]);
    }
}
