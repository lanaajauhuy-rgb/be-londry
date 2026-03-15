<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// PaymentController menangani pencatatan pembayaran order.
//
// ALUR KERJA saat ada pembayaran baru:
// 1. Validasi data yang dikirim
// 2. Cek apakah amount tidak melebihi sisa tagihan (amount_due)
// 3. Simpan record Payment baru
// 4. Update order: tambahkan amount ke amount_paid, kurangi dari amount_due
// 5. Update payment_status order (unpaid → partial → paid)
// Langkah 3-5 dibungkus DB::transaction supaya atomik.
//
// KENAPA payment_status tidak dikirim dari client?
// Karena server yang harus putuskan statusnya berdasarkan angka:
//   amount_due > 0  → 'partial'
//   amount_due == 0 → 'paid'
// Kalau client yang kirim, bisa saja salah atau sengaja dimanipulasi.
class PaymentController extends Controller
{
    // index() — GET /api/v1/orders/{order}/payments
    // Ambil semua pembayaran milik satu order tertentu.
    //
    // Parameter $order didapat dari Route Model Binding.
    // URL: /api/v1/orders/5/payments → $order = Order dengan id=5
    public function index(Order $order): JsonResponse
    {
        return response()->json([
            // Akses relasi payments yang sudah didefinisikan di Model Order.
            // Ini menghasilkan query: SELECT * FROM payments WHERE order_id = 5
            // latest() = urutkan dari yang terbaru (ORDER BY created_at DESC)
            'data' => $order->payments()->latest()->get(),
        ]);
    }

    // store() — POST /api/v1/orders/{order}/payments
    // Catat pembayaran baru untuk order tertentu.
    public function store(Request $request, Order $order): JsonResponse
    {
        // Cek dulu apakah order sudah lunas sebelum validasi data.
        // KENAPA di sini dan bukan di dalam transaction?
        // Karena ini adalah business rule yang perlu return pesan yang jelas ke client,
        // bukan technical error. Lebih baik dicek lebih awal = early return.
        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Order ini sudah lunas. Tidak bisa menambah pembayaran lagi.',
            ], 422);
        }

        $validated = $request->validate([
            // 'payment_date' boleh tidak dikirim, default ke waktu sekarang.
            // 'sometimes' = field ini opsional, boleh tidak ada di request.
            'payment_date' => ['sometimes', 'date'],
            // method harus salah satu dari 4 pilihan ini.
            'method'       => ['required', 'in:cash,transfer,qris,ewallet'],
            // amount wajib, harus angka positif lebih dari 0.
            // 'min:0.01' = minimal bayar 1 sen, tidak boleh 0.
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'reference_no' => ['sometimes', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
        ]);

        // Cek apakah jumlah yang dibayar tidak melebihi sisa tagihan.
        // amount_due = sisa yang masih harus dibayar.
        // Kalau bayar lebih dari amount_due = kelebihan bayar, tidak logis untuk laundry.
        if ($validated['amount'] > $order->amount_due) {
            return response()->json([
                'message'   => 'Jumlah pembayaran melebihi sisa tagihan.',
                'amount_due' => $order->amount_due, // kasih tahu client berapa yang bisa dibayar
            ], 422);
        }

        // Bungkus semua operasi DB dalam transaction.
        // Kalau satu langkah gagal, semua dibatalkan otomatis.
        $payment = DB::transaction(function () use ($order, $validated) {

            // Generate payment_number otomatis.
            // Format: PAY + tahun 2 digit + bulan + tanggal + jam + menit + detik
            // Contoh: PAY260315143022
            $paymentNumber = 'PAY' . now()->format('ymdHis');

            // Simpan record payment baru.
            $payment = Payment::create([
                'order_id'       => $order->id,
                'payment_number' => $paymentNumber,
                // Kalau payment_date tidak dikirim, pakai waktu sekarang.
                // now() mengembalikan Carbon object yang otomatis di-convert ke datetime.
                'payment_date'   => $validated['payment_date'] ?? now(),
                'method'         => $validated['method'],
                'amount'         => $validated['amount'],
                // Auth::id() = id user yang sedang login.
                // Ini perlu import: use Illuminate\Support\Facades\Auth;
                'paid_by_user_id' => auth()->id(),
                'reference_no'   => $validated['reference_no'] ?? null,
                'notes'          => $validated['notes']        ?? null,
            ]);

            // Hitung nilai baru untuk order setelah pembayaran ini.
            // (float) = paksa jadi tipe decimal supaya kalkulasi akurat.
            $newAmountPaid = (float) $order->amount_paid + (float) $validated['amount'];
            $newAmountDue  = (float) $order->total_amount - $newAmountPaid;

            // Tentukan payment_status baru berdasarkan sisa tagihan.
            // Logika ini di server, bukan dari client:
            //   amount_due > 0  → masih ada sisa tagihan → 'partial'
            //   amount_due <= 0 → sudah lunas → 'paid'
            // Pakai 0.01 bukan 0 untuk menghindari floating point precision issue.
            // Contoh: 0.1 + 0.2 di PHP bisa menghasilkan 0.30000000000000004, bukan 0.3 persis.
            $newPaymentStatus = $newAmountDue > 0.01 ? 'partial' : 'paid';

            // Update order dengan nilai yang baru dihitung.
            // increment() bisa dipakai sebagai alternatif yang lebih atomic:
            // $order->increment('amount_paid', $validated['amount'])
            // Tapi kita pakai update() supaya bisa update 2 field sekaligus.
            $order->update([
                'amount_paid'    => $newAmountPaid,
                'amount_due'     => max(0, $newAmountDue), // max(0) = tidak boleh negatif
                'payment_status' => $newPaymentStatus,
            ]);

            // Kembalikan object payment dari closure.
            // Ini yang akan jadi nilai $payment di luar transaction.
            return $payment;
        });

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat',
            'data'    => [
                'payment'        => $payment,
                // Load ulang order dari DB supaya menampilkan nilai terbaru.
                // ->fresh() = buat query baru ke DB dan kembalikan instance baru.
                // Berbeda dari ->refresh() yang update instance yang sudah ada.
                'order_summary'  => $order->fresh(['payments']),
            ],
        ], 201);
    }

    // show() — GET /api/v1/orders/{order}/payments/{payment}
    // Lihat detail satu pembayaran.
    //
    // Laravel otomatis validasi bahwa $payment memang milik $order ini.
    // Kalau payment id=3 bukan milik order id=5, Laravel return 404.
    // Ini disebut "Implicit Scoped Binding" — fitur bawaan Laravel.
    public function show(Order $order, Payment $payment): JsonResponse
    {
        return response()->json([
            'data' => $payment,
        ]);
    }

    // destroy() — DELETE /api/v1/orders/{order}/payments/{payment}
    // Hapus pembayaran dan kembalikan saldo order.
    //
    // KAPAN ini dipakai? Misal admin salah input pembayaran dan perlu dihapus.
    public function destroy(Order $order, Payment $payment): JsonResponse
    {
        DB::transaction(function () use ($order, $payment) {
            // Kurangi amount_paid order sebesar nilai payment yang dihapus.
            $newAmountPaid = (float) $order->amount_paid - (float) $payment->amount;
            $newAmountDue  = (float) $order->total_amount - $newAmountPaid;

            // Tentukan ulang payment_status setelah pembayaran ini dihapus.
            if ($newAmountPaid <= 0) {
                $newPaymentStatus = 'unpaid';
            } elseif ($newAmountDue > 0.01) {
                $newPaymentStatus = 'partial';
            } else {
                $newPaymentStatus = 'paid';
            }

            // Update order dulu sebelum hapus payment.
            // Urutan ini penting: kalau payment dihapus duluan tapi update order gagal,
            // data jadi tidak konsisten. Dengan transaction, kedua langkah atomik.
            $order->update([
                'amount_paid'    => max(0, $newAmountPaid),
                'amount_due'     => max(0, $newAmountDue),
                'payment_status' => $newPaymentStatus,
            ]);

            $payment->delete();
        });

        return response()->json([
            'message' => 'Pembayaran berhasil dihapus',
        ]);
    }
}
