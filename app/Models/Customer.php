<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $customer_code
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string $address
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCustomerCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
// ============================================================
// MODEL = "WAKIL" TABEL DI DATABASE
// ============================================================
// Setiap Model merepresentasikan satu tabel.
// Model Customer = tabel customers.
//
// Dengan extends Model, class ini otomatis punya method:
// - Customer::create()     → INSERT ke tabel
// - Customer::find(id)     → SELECT WHERE id = ?
// - Customer::findOrFail() → SELECT atau throw 404
// - Customer::where(...)   → SELECT dengan kondisi
// - Customer::latest()     → SELECT ORDER BY created_at DESC
// - $customer->update()   → UPDATE baris ini
// - $customer->delete()   → DELETE baris ini
// Semua ini dari Eloquent ORM bawaan Laravel.
// ============================================================
class Customer extends Model
{
    // $fillable = daftar kolom yang boleh diisi lewat create() atau update().
    // Ini proteksi "Mass Assignment" — tanpa $fillable, siapa saja bisa
    // kirim field apapun (misalnya 'is_admin' = true) dan langsung tersimpan.
    // Dengan $fillable, hanya field yang ada di sini yang diterima.
    //
    // Kebalikannya adalah $guarded = ['field_yang_diproteksi'].
    // Kebanyakan developer pilih salah satu: $fillable ATAU $guarded, tidak keduanya.
    protected $fillable = [
        'customer_code',
        'name',
        'phone',
        'email',
        'address',
        'notes',
    ];
}
