<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model Payment merepresentasikan tabel 'payments'.
// Satu Order bisa punya BANYAK Payment (relasi one-to-many).
// Contoh: Order #001 dibayar 2x — DP Rp50.000 dan pelunasan Rp100.000.
class Payment extends Model
{
    protected $fillable = [
        'order_id',        // foreign key ke tabel orders
        'payment_number',  // nomor unik pembayaran, format: PAY + timestamp
        'payment_date',    // tanggal transaksi terjadi
        'method',          // cara bayar: cash, transfer, qris, ewallet
        'amount',          // jumlah yang dibayar di transaksi ini
        'paid_by_user_id', // staff yang terima pembayaran (nullable)
        'reference_no',    // nomor referensi bank/payment gateway (nullable)
        'notes',           // catatan tambahan (nullable)
    ];

    // casts() = konversi tipe data otomatis saat baca dari DB.
    // Tanpa ini, 'amount' akan jadi string karena DB menyimpan semua angka sebagai teks.
    protected function casts(): array
    {
        return [
            // 'decimal:2' = pastikan amount selalu punya 2 angka di belakang koma
            'amount'       => 'decimal:2',
            // 'datetime' = convert ke Carbon object supaya bisa pakai method tanggal
            // Contoh: $payment->payment_date->format('d/m/Y H:i')
            'payment_date' => 'datetime',
        ];
    }

    // ============================================================
    // RELATIONSHIPS — relasi ke tabel lain
    // ============================================================

    // belongsTo = "Payment ini MILIK satu Order".
    // Karena tabel payments punya kolom order_id (foreign key ke orders).
    // Dengan relasi ini bisa akses: $payment->order->order_number
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // belongsTo ke User, tapi lewat kolom 'paid_by_user_id' bukan 'user_id'.
    // Argumen kedua wajib ditulis kalau nama kolomnya tidak mengikuti konvensi
    // nama_model + _id (konvensi: user_id, bukan paid_by_user_id).
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
