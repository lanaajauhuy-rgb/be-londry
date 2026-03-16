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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();

            // Relasi ke order.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // pickup / delivery
            $table->string('type');

            // Alamat pickup atau delivery.
            $table->text('address');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Kurir opsional, bisa null kalau belum di-assign.
            $table->foreignId('courier_user_id')->nullable()->constrained('users')->nullOnDelete();

            // ENUM supaya hanya nilai valid yang bisa masuk ke DB
            $table->enum('status', ['pending', 'on_the_way', 'done', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
