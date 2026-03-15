<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // order_number = nomor unik order yang terlihat oleh pelanggan.
            // Format yang dipakai di generateOrderNumber(): ORD + timestamp = maks 15 karakter.
            // Kolom dibuat VARCHAR(30) untuk fleksibilitas format ke depannya.
            $table->string('order_number', 30)->unique();

            // foreignId() = shorthand untuk kolom integer + foreign key constraint.
            // ->constrained('customers') = kolom ini referensi ke tabel customers.id
            // ->cascadeOnDelete() = kalau customer dihapus, semua ordernya ikut terhapus.
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // ->nullable() = kolom ini boleh kosong (NULL).
            // ->nullOnDelete() = kalau user yang menerima order dihapus,
            //                    kolom ini di-set NULL (bukan ikut terhapus).
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('outlet_name')->nullable();
            $table->dateTime('order_date');
            $table->dateTime('estimated_done_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // status order disimpan sebagai string bebas supaya fleksibel.
            // Nilai yang disarankan (dari task.md):
            // pending, received, washing, drying, ironing, ready, delivered, completed, cancelled
            $table->string('status')->default('pending');

            // payment_status pakai ENUM karena nilainya terbatas dan sudah pasti.
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');

            // decimal(total_digit, decimal_places)
            // decimal(15, 2) = bisa tampung angka sampai 9.999.999.999.999,99
            // PERBAIKAN: sebelumnya decimal(15) tanpa decimal_places = tidak ada koma.
            // Untuk harga rupiah kita butuh 2 angka di belakang koma.
            $table->decimal('subtotal', 15, 2)->default(0);

            // TAMBAHAN dari task.md yang sebelumnya belum ada:
            // discount_amount = potongan harga (voucher, promo, dll)
            $table->decimal('discount_amount', 15, 2)->default(0);
            // tax_amount = pajak yang dikenakan ke order (PPN, dll)
            $table->decimal('tax_amount', 15, 2)->default(0);
            // extra_charge_amount = biaya tambahan (ongkos jemput, ekspres fee, dll)
            $table->decimal('extra_charge_amount', 15, 2)->default(0);

            // total_amount = subtotal - discount + tax + extra_charge
            // Rumus ini dihitung di controller, bukan di DB.
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            // amount_due = total_amount - amount_paid (sisa yang harus dibayar)
            $table->decimal('amount_due', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
