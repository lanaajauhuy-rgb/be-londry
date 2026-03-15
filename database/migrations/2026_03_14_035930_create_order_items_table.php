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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();

            // Tipe layanan — hanya boleh 'kiloan' atau 'per_item'.
            // 'kiloan'   = dihitung per berat (kg)
            // 'per_item' = dihitung per satuan / flat
            $table->enum('service_type', ['kiloan', 'per_item']);

            $table->string('item_name')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            // CATATAN: CHECK constraint sengaja tidak ditambahkan di sini.
            // Alasan: kita pakai SQLite untuk development, dan SQLite tidak mendukung
            // ALTER TABLE ADD CONSTRAINT, sedangkan $table->check() tidak tersedia
            // di semua versi Laravel.
            //
            // Validasi data sudah ditangani di dua tempat:
            // 1. Controller (OrderItemController) — cek weight_kg saat service_type kiloan.
            // 2. Enum kolom service_type — Laravel/DB otomatis tolak nilai selain 'kiloan'/'per_item'.
            //
            // Kalau nanti pindah ke MySQL production, CHECK constraint bisa ditambahkan
            // lewat migration baru dengan DB::statement() yang khusus dijalankan di MySQL.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
