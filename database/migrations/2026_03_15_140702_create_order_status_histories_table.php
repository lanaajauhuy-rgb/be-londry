<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration ini membuat tabel 'order_status_histories'.
// Tabel ini menyimpan SETIAP perubahan status yang terjadi pada order.
//
// KENAPA perlu tabel terpisah untuk history status?
// Analogi: order seperti paket kiriman. Kamu ingin tahu:
// - Jam 08:00 → status "received"
// - Jam 10:00 → status "washing"
// - Jam 14:00 → status "drying"
// Kalau hanya simpan 1 kolom status di tabel orders, kamu hanya bisa
// lihat status SEKARANG. History-nya hilang.
// Dengan tabel ini, semua perubahan status tercatat lengkap = AUDIT TRAIL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();

            // Setiap record history milik satu order.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // Status baru yang di-set pada saat record ini dibuat.
            // Nilai: pending, received, washing, drying, ironing,
            //        ready, delivered, completed, cancelled
            $table->string('status');

            // Siapa yang mengubah status ini.
            // nullable karena bisa sistem otomatis (cron job) yang ubah status.
            // nullOnDelete() = kalau user dihapus, kolom ini jadi NULL tapi
            //                  record history tetap ada (tidak ikut terhapus).
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Kapan perubahan status ini terjadi.
            // Berbeda dari created_at: changed_at bisa di-set manual oleh admin
            // kalau misal lupa input dan baru diinput belakangan.
            $table->dateTime('changed_at');

            // Catatan opsional: alasan perubahan, kondisi barang, dll.
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
