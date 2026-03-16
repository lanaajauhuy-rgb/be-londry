<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// OrderStatusController menangani perubahan dan riwayat status order.
//
// KENAPA dipisah dari OrderController?
// Separation of Concerns — setiap controller punya tanggung jawab yang fokus.
// OrderController  = kelola data order (buat, edit, hapus)
// OrderStatusController = kelola status order (ubah status + catat history)
//
// Analogi: di toko, ada kasir (OrderController) dan operator laundry (OrderStatusController).
// Keduanya berurusan dengan order tapi tugas mereka berbeda.
class OrderStatusController extends Controller
{
    // index() — GET /api/v1/orders/{order}/statuses
    // Ambil semua riwayat perubahan status milik satu order.
    // Ini adalah "timeline" perjalanan order dari awal sampai sekarang.
    public function index(Order $order): JsonResponse
    {
        return response()->json([
            // statusHistories() sudah didefinisikan di Model Order.
            // Sudah diurutkan by changed_at ASC di dalam relasi,
            // jadi response-nya adalah timeline dari awal ke akhir.
            'data' => $order->statusHistories()->get(),
        ]);
    }

    // store() — POST /api/v1/orders/{order}/statuses
    // Ubah status order dan otomatis catat history perubahan.
    //
    // INI adalah method paling penting di controller ini.
    // Setiap kali status berubah, HARUS ada record baru di order_status_histories.
    // Jangan update status order langsung lewat OrderController
    // karena history tidak akan tercatat.
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            // Rule::in() = status baru harus salah satu dari list ini.
            // Daftar ini harus sama persis dengan yang ada di OrderController
            // supaya konsisten di seluruh aplikasi.
            'status'     => ['required', Rule::in([
                'pending', 'received', 'washing', 'drying',
                'ironing', 'ready', 'delivered', 'completed', 'cancelled',
            ])],
            // changed_at boleh tidak dikirim, default ke sekarang.
            // 'sometimes' = opsional, boleh tidak ada di request.
            // Ini berguna kalau admin lupa input dan baru memasukkan data setelahnya.
            'changed_at' => ['sometimes', 'date'],
            'notes'      => ['nullable', 'string'],
        ]);
        if ($order->status === 'completed') {
            return response()->json([
                'message' => 'Order ini sudah selesai. Tidak bisa mengubah status lagi.',
            ], 422);
        }

        // Cek apakah status yang dikirim sama dengan status sekarang.
        // Tidak ada gunanya update ke status yang sama = tidak ada perubahan.
        if ($order->status === $validated['status']) {
            return response()->json([
                'message' => 'Status order sudah ' . $order->status . '. Tidak ada perubahan.',
            ], 422);
        }

        // Bungkus dalam transaction karena ada 2 operasi DB:
        // 1. Update status di tabel orders
        // 2. Insert record baru di tabel order_status_histories
        // Kalau salah satu gagal, keduanya dibatalkan.
        DB::transaction(function () use ($order, $validated) {

            // Tentukan kapan perubahan ini terjadi.
            // Kalau admin kirim changed_at → pakai itu.
            // Kalau tidak → pakai waktu sekarang.
            $changedAt = $validated['changed_at'] ?? now();

            // Array data yang akan di-update ke tabel orders.
            // Kita siapkan dulu sebelum eksekusi supaya kodenya lebih rapi.
            $orderUpdate = [
                'status' => $validated['status'],
            ];

            // Side effect berdasarkan status baru:
            // Kalau status jadi 'completed' → isi completed_at otomatis.
            // Ini mencatat waktu order benar-benar selesai.
            if ($validated['status'] === 'completed') {
                $orderUpdate['completed_at'] = $changedAt;
            }

            // Kalau status di-cancel → pastikan completed_at dikosongkan.
            // Order yang di-cancel tidak dianggap selesai.
            if ($validated['status'] === 'cancelled') {
                $orderUpdate['completed_at'] = null;
            }

            // Update status (dan completed_at kalau ada) di tabel orders.
            $order->update($orderUpdate);

            // Catat perubahan status ke tabel order_status_histories.
            // Record ini TIDAK BOLEH dihapus — ini adalah audit trail permanen.
            // auth()->id() = id user yang sedang login (yang melakukan perubahan ini).
            OrderStatusHistory::create([
                'order_id'           => $order->id,
                'status'             => $validated['status'],
                'changed_by_user_id' => auth()->id(),
                'changed_at'         => $changedAt,
                'notes'              => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Status order berhasil diubah',
            'data'    => [
                // ->fresh() = query ulang ke DB untuk dapat data terbaru.
                // Pakai eager loading 'statusHistories' supaya response
                // langsung include semua history, tidak perlu request lagi.
                'order'    => $order->fresh(['statusHistories']),
            ],
        ]);
    }

    // show() — GET /api/v1/orders/{order}/statuses/{orderStatusHistory}
    // Lihat detail satu record perubahan status.
    //
    // Laravel otomatis bind $orderStatusHistory dari URL berdasarkan id.
    // Dan otomatis validasi bahwa history ini milik $order — kalau tidak, 404.
    // Ini "Implicit Scoped Binding" bawaan Laravel.
    public function show(Order $order, OrderStatusHistory $orderStatusHistory): JsonResponse
    {
        return response()->json([
            'data' => $orderStatusHistory,
        ]);
    }

    // CATATAN: Tidak ada update() dan destroy() di sini.
    //
    // KENAPA tidak ada destroy()?
    // History status adalah audit trail — catatan permanen yang tidak boleh dihapus.
    // Kalau admin salah input status, solusinya adalah buat record status baru
    // yang benar, bukan hapus yang lama.
    // Contoh: salah set 'completed', harusnya 'ready' → buat record baru 'ready'.
    //
    // KENAPA tidak ada update()?
    // Alasan yang sama — history tidak boleh diubah retroaktif.
    // Kalau boleh diubah, audit trail tidak bisa dipercaya lagi.
}
