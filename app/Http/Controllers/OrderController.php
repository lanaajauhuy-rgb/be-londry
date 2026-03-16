<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// OrderController menangani CRUD order lewat endpoint internal (admin only).
// Berbeda dengan PublicOrderController yang untuk pelanggan umum,
// controller ini dipakai admin untuk kelola order secara langsung.
class OrderController extends Controller
{
    // index() — GET /api/v1/orders
    // Mendukung filter, search, dan pagination.
    //
    // Query params:
    //   ?search=ORD001         → cari by order_number
    //   ?status=washing        → filter by status
    //   ?payment_status=unpaid → filter by payment_status
    //   ?customer_id=3         → filter by customer
    //   ?per_page=20           → jumlah per halaman
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'         => ['sometimes', 'string', 'max:100'],
            'status'         => ['sometimes', 'string'],
            'payment_status' => ['sometimes', 'in:unpaid,partial,paid,refunded'],
            'customer_id'    => ['sometimes', 'integer', 'exists:customers,id'],
            'per_page'       => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::with('customer:id,name,phone,customer_code')
                      ->latest('order_date');

        if (! empty($validated['search'])) {
            $query->where('order_number', 'LIKE', "%{$validated['search']}%");
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }
        if (! empty($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 15));
    }

    // store() — POST /api/v1/orders
    // Buat order baru secara manual lewat panel admin.
    // Berbeda dengan PublicOrderController: di sini tidak ada auto-generate
    // order_number — admin harus kirim order_number sendiri.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // max:30 sesuai kolom VARCHAR(30) di migration.
            // BUG LAMA: dulu max:8, padahal format order_number bisa 15 karakter.
            // Selalu samakan max validation dengan panjang kolom di database.
            'order_number'        => ['required', 'string', 'max:30', 'unique:orders,order_number'],
            // 'exists:customers,id' = cek ke tabel customers, pastikan id ini ada.
            // Kalau kirim customer_id yang tidak ada → error 422, bukan error DB.
            // Ini validasi di level PHP sebelum data menyentuh database.
            'customer_id'         => ['required', 'exists:customers,id'],
            'received_by_user_id' => ['nullable', 'exists:users,id'],
            'outlet_name'         => ['nullable', 'string', 'max:255'],
            // 'date' = validasi format tanggal yang dikenali PHP.
            // Menerima: '2026-03-15', '15 March 2026', timestamp, dll.
            'order_date'          => ['required', 'date'],
            'estimated_done_at'   => ['nullable', 'date'],
            'completed_at'        => ['nullable', 'date'],
            // status divalidasi pakai Rule::in() supaya hanya nilai yang valid diterima.
            // Daftar status yang disarankan task.md:
            // pending, received, washing, drying, ironing, ready, delivered, completed, cancelled
            'status'              => ['required', Rule::in([
                'pending', 'received', 'washing', 'drying',
                'ironing', 'ready', 'delivered', 'completed', 'cancelled',
            ])],
            // Rule::in([...]) = nilai HARUS salah satu dari list ini.
            // Kalau kirim 'lunas' misalnya → error 422 karena tidak ada di list.
            'payment_status'      => ['required', Rule::in(['unpaid', 'partial', 'paid', 'refunded'])],
            // 'numeric' = terima integer atau decimal (5 atau 5.5).
            // 'min:0' = tidak boleh negatif, karena harga tidak mungkin minus.
            'subtotal'            => ['required', 'numeric', 'min:0'],
            // TAMBAHAN: 3 kolom baru dari task.md
            // nullable karena tidak semua order punya diskon/pajak/biaya tambahan.
            // 'sometimes' = field ini boleh tidak dikirim sama sekali (berbeda dari nullable
            // yang tetap harus dikirim walau nilainya null).
            'discount_amount'     => ['sometimes', 'numeric', 'min:0'],
            'tax_amount'          => ['sometimes', 'numeric', 'min:0'],
            'extra_charge_amount' => ['sometimes', 'numeric', 'min:0'],
            // amount_paid boleh dikirim (misal order langsung lunas saat dibuat)
            // tapi default-nya 0 kalau tidak dikirim.
            'amount_paid'         => ['sometimes', 'numeric', 'min:0'],
            'notes'               => ['nullable', 'string'],
        ]);

        // Hitung total_amount dan amount_due di server, bukan dari client.
        // KENAPA? Supaya angka selalu konsisten dan tidak bisa dimanipulasi client.
        //
        // '?? 0' = kalau field tidak dikirim (karena 'sometimes'), pakai 0 sebagai default.
        // Ini cara PHP menghandle nilai yang mungkin tidak ada di array.
        $subtotal           = (float) $validated['subtotal'];
        $discountAmount     = (float) ($validated['discount_amount']     ?? 0);
        $taxAmount          = (float) ($validated['tax_amount']          ?? 0);
        $extraChargeAmount  = (float) ($validated['extra_charge_amount'] ?? 0);
        $amountPaid         = (float) ($validated['amount_paid']         ?? 0);

        // Rumus total: subtotal dikurangi diskon, ditambah pajak dan biaya ekstra.
        $totalAmount = $subtotal - $discountAmount + $taxAmount + $extraChargeAmount;

        // amount_due = sisa yang masih harus dibayar.
        $amountDue = $totalAmount - $amountPaid;

        $order = Order::create([
            // Spread operator '...$validated' tidak dipakai di sini karena kita perlu
            // kontrol penuh atas field mana yang masuk, terutama total_amount dan amount_due
            // yang dihitung di atas — bukan dari client.
            'order_number'        => $validated['order_number'],
            'customer_id'         => $validated['customer_id'],
            'received_by_user_id' => $validated['received_by_user_id'] ?? null,
            'outlet_name'         => $validated['outlet_name']         ?? null,
            'order_date'          => $validated['order_date'],
            'estimated_done_at'   => $validated['estimated_done_at']   ?? null,
            'completed_at'        => $validated['completed_at']        ?? null,
            'status'              => $validated['status'],
            'payment_status'      => $validated['payment_status'],
            'subtotal'            => $subtotal,
            'discount_amount'     => $discountAmount,
            'tax_amount'          => $taxAmount,
            'extra_charge_amount' => $extraChargeAmount,
            'total_amount'        => $totalAmount,   // dihitung server
            'amount_paid'         => $amountPaid,
            'amount_due'          => $amountDue,     // dihitung server
            'notes'               => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Order berhasil dibuat',
            'data'    => $order,
        ], 201); // 201 = HTTP Created
    }

    // show() — GET /api/v1/orders/{id}
    // Mengembalikan detail ORDER LENGKAP: items, payments, status history, delivery.
    // Ini yang dipakai saat buka halaman detail order di frontend.
    public function show(Order $order): JsonResponse
    {
        // Eager load semua relasi yang dibutuhkan dalam SATU query roundtrip.
        // Tanpa with(), setiap akses relasi = query DB terpisah (N+1 problem).
        $order->load([
            'customer:id,name,phone,customer_code,email,address',
            'receivedBy:id,name,role',
            'items.service:id,name,code,pricing_model',
            'payments' => fn ($q) => $q->latest(),
            'statusHistories' => fn ($q) => $q->with('changedBy:id,name')->orderBy('changed_at'),
            'deliveries',
        ]);

        return response()->json([
            'data' => $order,
        ]);
    }

    // update() — PUT /api/v1/orders/{id}
    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => [
                'required',
                'string',
                'max:30',
                // ->ignore($order->id) = saat cek unique, abaikan baris order ini sendiri.
                // KENAPA perlu ignore?
                // Contoh: order id=3 punya order_number='ORD001'.
                // Kalau update tanpa ignore, validasi unique akan error karena
                // 'ORD001' sudah ada di DB — padahal itu milik order itu sendiri.
                Rule::unique('orders', 'order_number')->ignore($order->id),
            ],
            'customer_id'         => ['required', 'exists:customers,id'],
            'received_by_user_id' => ['nullable', 'exists:users,id'],
            'outlet_name'         => ['nullable', 'string', 'max:255'],
            'order_date'          => ['required', 'date'],
            'estimated_done_at'   => ['nullable', 'date'],
            'completed_at'        => ['nullable', 'date'],
            'status'              => ['required', Rule::in([
                'pending', 'received', 'washing', 'drying',
                'ironing', 'ready', 'delivered', 'completed', 'cancelled',
            ])],
            'payment_status'      => ['required', Rule::in(['unpaid', 'partial', 'paid', 'refunded'])],
            'subtotal'            => ['required', 'numeric', 'min:0'],
            // 'sometimes' = boleh tidak dikirim, kalau tidak ada pakai nilai lama di DB.
            'discount_amount'     => ['sometimes', 'numeric', 'min:0'],
            'tax_amount'          => ['sometimes', 'numeric', 'min:0'],
            'extra_charge_amount' => ['sometimes', 'numeric', 'min:0'],
            // amount_paid tidak perlu dikirim lewat endpoint ini.
            // Pembayaran dihandle oleh PaymentController yang otomatis update amount_paid.
            // Tapi tetap bisa di-override admin kalau perlu koreksi manual.
            'amount_paid'         => ['sometimes', 'numeric', 'min:0'],
            'notes'               => ['nullable', 'string'],
        ]);

        // Untuk update, pakai nilai lama dari DB kalau field tidak dikirim.
        // '?? $order->field' = kalau tidak ada di request, pakai nilai yang sudah ada di record ini.
        // Ini penting supaya update partial bisa dilakukan tanpa harus kirim semua field.
        $subtotal          = (float) $validated['subtotal'];
        $discountAmount    = (float) ($validated['discount_amount']     ?? $order->discount_amount);
        $taxAmount         = (float) ($validated['tax_amount']          ?? $order->tax_amount);
        $extraChargeAmount = (float) ($validated['extra_charge_amount'] ?? $order->extra_charge_amount);
        $amountPaid        = (float) ($validated['amount_paid']         ?? $order->amount_paid);

        // Hitung ulang total dan due setiap kali ada update.
        // Ini memastikan total selalu konsisten walau ada perubahan diskon atau pajak.
        $totalAmount = $subtotal - $discountAmount + $taxAmount + $extraChargeAmount;
        $amountDue   = $totalAmount - $amountPaid;

        // $order sudah spesifik ke satu baris (dari Route Model Binding).
        // ->update() langsung eksekusi UPDATE SQL untuk baris itu.
        $order->update([
            'order_number'        => $validated['order_number'],
            'customer_id'         => $validated['customer_id'],
            'received_by_user_id' => $validated['received_by_user_id'] ?? null,
            'outlet_name'         => $validated['outlet_name']         ?? null,
            'order_date'          => $validated['order_date'],
            'estimated_done_at'   => $validated['estimated_done_at']   ?? null,
            'completed_at'        => $validated['completed_at']        ?? null,
            'status'              => $validated['status'],
            'payment_status'      => $validated['payment_status'],
            'subtotal'            => $subtotal,
            'discount_amount'     => $discountAmount,
            'tax_amount'          => $taxAmount,
            'extra_charge_amount' => $extraChargeAmount,
            'total_amount'        => $totalAmount,
            'amount_paid'         => $amountPaid,
            'amount_due'          => $amountDue,
            'notes'               => $validated['notes'] ?? null,
        ]);

        // ->refresh() = reload data dari DB supaya response menampilkan nilai terbaru.
        // Tanpa ini, $order masih menyimpan nilai lama sebelum update.
        $order->refresh();

        return response()->json([
            'message' => 'Order berhasil diupdate',
            'data'    => $order,
        ]); // default 200 OK
    }

    // destroy() — DELETE /api/v1/orders/{id}
    public function destroy(Order $order): JsonResponse
    {
        // Perhatian: menghapus order akan CASCADE DELETE ke order_items dan deliveries
        // karena foreign key di migration pakai ->cascadeOnDelete().
        // Artinya: hapus 1 order = otomatis hapus semua order_items dan deliveries miliknya.
        $order->delete();

        return response()->json([
            'message' => 'Order berhasil dihapus',
        ]);
    }
}
