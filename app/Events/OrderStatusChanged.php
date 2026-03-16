<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// OrderStatusChanged = Event "pengumuman" bahwa status order berubah.
//
// CARA KERJA Event di Laravel:
// 1. Controller lempar:  event(new OrderStatusChanged($order, $old, $new))
// 2. Laravel cari semua Listener yang subscribe ke event ini
// 3. Semua Listener dijalankan otomatis
//
// Keuntungan: Controller tidak perlu tahu siapa yang bereaksi.
// Mau tambah notifikasi WA, email, push? Cukup buat Listener baru —
// tidak perlu sentuh Controller sama sekali.
class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        // Order yang statusnya berubah
        public readonly Order  $order,
        // Status lama — untuk tahu dari mana asalnya
        public readonly string $oldStatus,
        // Status baru — untuk tentukan isi notifikasi
        public readonly string $newStatus,
    ) {}
}
