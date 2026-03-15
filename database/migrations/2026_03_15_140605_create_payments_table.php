<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration ini membuat tabel 'payments'.
// Tabel payments menyimpan setiap transaksi pembayaran untuk sebuah order.
//
// KENAPA payments dipisah dari orders?
// Karena satu order bisa dibayar BEBERAPA KALI:
// - DP dulu 50%
// - Pelunasan 50% saat ambil
// Kalau disatukan di tabel orders, tidak bisa track history pembayaran.
// Dengan tabel terpisah, setiap transaksi bayar punya record sendiri.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Setiap payment milik satu order.
            // cascadeOnDelete() = kalau order dihapus, semua payment-nya ikut terhapus.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // payment_number = nomor unik pembayaran, untuk referensi/kwitansi.
            // Contoh format: PAY260315001
            $table->string('payment_number', 30)->unique();

            // Tanggal transaksi pembayaran terjadi.
            $table->dateTime('payment_date');

            // method = cara bayar. ENUM karena pilihannya terbatas dan sudah pasti.
            // cash     = bayar tunai
            // transfer = transfer bank
            // qris     = scan QR code (GoPay, OVO, Dana, dll via QRIS)
            // ewallet  = dompet digital lainnya
            $table->enum('method', ['cash', 'transfer', 'qris', 'ewallet']);

            // Jumlah uang yang dibayarkan di transaksi ini.
            // decimal(15, 2) = bisa tampung sampai 9.999.999.999.999,99
            $table->decimal('amount', 15, 2);

            // Staff yang menerima/memproses pembayaran ini.
            // nullable karena bisa saja payment dilakukan otomatis (online).
            // nullOnDelete() = kalau user dihapus, kolom ini di-set NULL.
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // reference_no = nomor referensi dari pihak ketiga.
            // Contoh: nomor transaksi dari bank, kode unik transfer, dll.
            // nullable karena hanya relevan untuk transfer/qris/ewallet.
            $table->string('reference_no', 100)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    // down() dipanggil saat kamu jalankan: php artisan migrate:rollback
    // Tugasnya: BATALKAN apa yang dilakukan up().
    // Kalau up() buat tabel → down() hapus tabel.
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
