<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $order_number
 * @property int $customer_id
 * @property int|null $received_by_user_id
 * @property string|null $outlet_name
 * @property string $order_date
 * @property string|null $estimated_done_at
 * @property string|null $completed_at
 * @property string $status
 * @property string $payment_status
 * @property numeric $subtotal
 * @property numeric $total_amount
 * @property numeric $amount_paid
 * @property numeric $amount_due
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAmountDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEstimatedDoneAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOutletName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereReceivedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @mixin \Eloquent
 */
// Model Order merepresentasikan tabel 'orders' di database.
// Satu baris di tabel orders = satu object Order di PHP.
class Order extends Model
{
    // $fillable mendefinisikan kolom mana yang boleh diisi secara massal
    // lewat Order::create([...]) atau $order->update([...]).
    //
    // KENAPA perlu $fillable?
    // Proteksi "Mass Assignment" — tanpa ini, hacker bisa kirim field
    // berbahaya seperti 'is_admin=true' dan langsung tersimpan ke DB.
    // Dengan $fillable, hanya field di bawah ini yang diterima.
    //
    // ALTERNATIF: $guarded = [] artinya semua kolom boleh diisi.
    // Tidak disarankan karena tidak aman di production.
    protected $fillable = [
        'order_number',        // nomor unik order, format: ORD + timestamp
        'customer_id',         // foreign key ke tabel customers
        'received_by_user_id', // foreign key ke tabel users (bisa null)
        'outlet_name',         // nama outlet (bisa null)
        'order_date',          // tanggal order masuk
        'estimated_done_at',   // estimasi selesai (bisa null)
        'completed_at',        // tanggal selesai aktual (bisa null)
        'status',              // status order: pending, processing, done, dll
        'payment_status',      // status bayar: unpaid, partial, paid, refunded
        'subtotal',              // total sebelum diskon/tambahan
        'discount_amount',       // potongan harga (voucher, promo)
        'tax_amount',            // pajak (PPN, dll)
        'extra_charge_amount',   // biaya tambahan (ongkir, ekspres fee)
        'total_amount',          // total akhir = subtotal - discount + tax + extra
        'amount_paid',           // jumlah yang sudah dibayar
        'amount_due',            // sisa yang belum dibayar
        'notes',                 // catatan tambahan (bisa null)
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================
    // hasMany = "Order ini PUNYA BANYAK ...".
    // Kebalikan dari belongsTo yang ada di model lain.
    // Dengan relasi ini bisa akses:
    //   $order->items         → semua item di order ini
    //   $order->payments      → semua pembayaran order ini
    //   $order->statusHistories → semua riwayat status order ini
    //   $order->deliveries    → semua pickup/delivery order ini

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // belongsTo karena orders punya kolom customer_id
        return $this->belongsTo(Customer::class);
    }

    public function receivedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // Argumen kedua karena nama kolom bukan 'user_id' (konvensi default)
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // hasMany karena satu order bisa punya banyak order_items
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // Urutkan dari yang terlama ke terbaru supaya bisa baca timeline dengan benar
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
