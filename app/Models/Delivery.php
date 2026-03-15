<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property string $address
 * @property string|null $scheduled_at
 * @property string|null $completed_at
 * @property int|null $courier_user_id
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $courier
 * @property-read \App\Models\Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereCourierUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereUpdatedAt($value)
 * @mixin \Eloquent
 */
// Delivery adalah satu-satunya Model yang punya Relationship (relasi antar tabel).
// Relasi = cara Laravel menghubungkan data dari tabel yang berbeda.
class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'address',
        'scheduled_at',
        'completed_at',
        'courier_user_id',
        'status',
        'notes',
    ];

    // belongsTo = relasi "milik siapa".
    // Delivery milik Order — karena tabel deliveries punya kolom order_id.
    // Dengan relasi ini, kamu bisa akses: $delivery->order->order_number
    // Laravel otomatis JOIN tabel dan ambil data Order yang berelasi.
    //
    // Kebalikannya adalah hasMany di Order:
    // public function deliveries() { return $this->hasMany(Delivery::class); }
    // Lalu bisa akses: $order->deliveries (semua delivery milik order itu)
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
        // Laravel tahu foreign key-nya adalah 'order_id' karena konvensi penamaan:
        // nama model (Order) + '_id' = order_id.
        // Kalau nama kolomnya berbeda, tulis: belongsTo(Order::class, 'nama_kolom_lain')
    }

    // Relasi ke User, tapi bukan lewat kolom 'user_id' standar.
    // Kolomnya adalah 'courier_user_id', jadi harus ditulis eksplisit sebagai argumen kedua.
    // Tanpa argumen kedua, Laravel akan cari kolom 'user_id' dan tidak ketemu.
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_user_id');
    }
}
