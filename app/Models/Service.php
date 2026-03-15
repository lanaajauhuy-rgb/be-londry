<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $pricing_model
 * @property numeric $unit_price
 * @property int|null $estimated_hours
 * @property string|null $description
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereEstimatedHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service wherePricingModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereUpdatedAt($value)
 * @mixin \Eloquent
 */
// Model Service merepresentasikan tabel 'services'.
// Service = jenis layanan yang ditawarkan laundry.
// Contoh: Cuci Kiloan (per_kg), Cuci Sepatu (per_item), Express Wash (flat).
class Service extends Model
{
    protected $fillable = [
        'code',          // kode unik layanan, maks 7 karakter. Contoh: 'CK001'
        'name',          // nama layanan. Contoh: 'Cuci Kiloan'
        // pricing_model menentukan cara hitung harga:
        //   'per_kg'   → harga = weight_kg * unit_price
        //   'per_item' → harga = qty * unit_price
        //   'flat'     → harga = unit_price (tetap, tidak peduli berat/qty)
        'pricing_model',
        'unit_price',      // harga dasar per kg / per item / flat
        'estimated_hours', // estimasi jam pengerjaan (bisa null)
        'description',     // deskripsi layanan (bisa null)
        // is_active = boolean, menentukan apakah layanan ini bisa dipesan.
        // KENAPA tidak dihapus saja kalau tidak aktif?
        // Karena data order_items lama masih referensi service ini.
        // Kalau dihapus, data historis bisa rusak (orphan records).
        // Lebih aman nonaktifkan daripada hapus.
        'is_active',
    ];
}
