<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $service_id
 * @property string $service_type
 * @property string|null $item_name
 * @property int $qty
 * @property numeric|null $weight_kg
 * @property numeric $unit_price
 * @property numeric $line_total
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereLineTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereServiceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereWeightKg($value)
 * @mixin \Eloquent
 */
// Model OrderItem merepresentasikan tabel 'order_items'.
// Satu Order bisa punya banyak OrderItem (relasi one-to-many).
// Contoh: Order #001 berisi 2 item: Cuci Kiloan + Setrika.
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',     // foreign key ke tabel orders
        'service_id',   // foreign key ke tabel services
        // service_type = ENUM, hanya boleh 'kiloan' atau 'per_item'.
        // Nilainya di-mapping dari pricing_model di tabel services:
        //   services.per_kg   → order_items.kiloan
        //   services.per_item → order_items.per_item
        //   services.flat     → order_items.per_item
        'service_type',
        'item_name',  // nama layanan saat order dibuat (snapshot)
        'qty',        // jumlah unit
        'weight_kg',  // berat dalam kg, wajib diisi kalau service_type = kiloan
        'unit_price', // harga per unit/kg saat order dibuat (snapshot)
        // KENAPA unit_price & item_name disimpan di sini (bukan cuma di services)?
        // Karena harga service bisa berubah kapan saja. Dengan menyimpan snapshot
        // harga di order_items, histori harga tetap akurat walau harga service diubah.
        'line_total', // total harga item ini = weight_kg * unit_price (kiloan)
                      //                     atau qty * unit_price (per_item)
        'notes',      // catatan per item (bisa null)
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    // OrderItem milik satu Order.
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // OrderItem referensi ke satu Service.
    // Dibutuhkan supaya bisa eager load: OrderItem::with('service')
    // yang dipakai di ReportController::getTopServices().
    public function service(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
