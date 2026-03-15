<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model OrderStatusHistory merepresentasikan tabel 'order_status_histories'.
// Setiap kali status order berubah, satu record baru dibuat di tabel ini.
// Ini adalah pattern "Audit Trail" — setiap perubahan dicatat permanen.
class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',           // foreign key ke tabel orders
        'status',             // status baru yang di-set
        'changed_by_user_id', // siapa yang ubah (nullable = sistem otomatis)
        'changed_at',         // kapan perubahan terjadi
        'notes',              // alasan atau catatan perubahan (nullable)
    ];

    protected function casts(): array
    {
        return [
            // 'datetime' = convert ke Carbon object
            // Dengan ini bisa: $history->changed_at->diffForHumans() → "2 hours ago"
            'changed_at' => 'datetime',
        ];
    }

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    // Setiap history milik satu Order.
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Siapa yang mengubah status ini.
    // Nama method 'changedBy' lebih deskriptif dari 'user'.
    // Argumen kedua 'changed_by_user_id' karena nama kolom tidak standar.
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
