# Database Schema — Laundry Lastri

Dokumentasi lengkap skema database, relasi antar tabel, dan penjelasan setiap kolom.

> **Database Development:** SQLite (`database/database.sqlite`)
> **Database Production (target):** MySQL 8+ atau PostgreSQL 15+

---

## Diagram Relasi Antar Tabel

```
users ──────────────┬──── orders.received_by_user_id
                    ├──── deliveries.courier_user_id
                    └──── payments.paid_by_user_id

customers ──────────── orders.customer_id

orders ─────────────┬──── order_items.order_id    (cascade delete)
                    ├──── payments.order_id        (cascade delete)
                    └──── deliveries.order_id      (cascade delete)

services ───────────── order_items.service_id      (cascade delete)
```

---

## Tabel `users`

Menyimpan akun staff/admin backend. Bukan untuk pelanggan.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| name | varchar(255) | ❌ | Nama lengkap |
| email | varchar(255) UNIQUE | ❌ | Email untuk login |
| password | varchar(255) | ❌ | Hash bcrypt |
| role | enum | ❌ | `admin`, `cashier`, `operator`, `courier` |
| phone | varchar(20) | ✅ | Nomor HP |
| is_active | boolean | ❌ | Default: `true`. False = tidak bisa login |
| last_login_at | timestamp | ✅ | Waktu login terakhir |
| remember_token | varchar(100) | ✅ | Token "ingat saya" |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |
| deleted_at | timestamp | ✅ | Soft delete |

**Nilai role:**
- `admin` — akses penuh ke semua fitur
- `cashier` — input order dan pembayaran
- `operator` — update status order
- `courier` — update status delivery

---

## Tabel `customers`

Menyimpan data pelanggan laundry.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| customer_code | varchar(10) UNIQUE | ❌ | Format: `CUS00001` |
| name | varchar(50) | ❌ | Nama pelanggan |
| phone | varchar(13) INDEX | ❌ | Dipakai untuk identifikasi saat order publik |
| email | varchar(50) | ✅ | — |
| address | varchar(500) | ❌ | Alamat lengkap |
| notes | varchar(255) | ✅ | Catatan khusus |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |

**Catatan:**
- `phone` di-index karena dipakai sebagai kunci pencarian di `PublicOrderController`
- `customer_code` di-generate otomatis format `CUS` + 5 digit (contoh: `CUS00001`)

---

## Tabel `services`

Master data layanan laundry yang tersedia.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| code | varchar(7) UNIQUE | ❌ | Kode layanan, contoh: `SVC0001` |
| name | varchar(50) | ❌ | Nama layanan |
| pricing_model | enum | ❌ | `per_kg`, `per_item`, `flat` |
| unit_price | decimal(10) | ❌ | Harga satuan |
| estimated_hours | integer | ✅ | Estimasi jam pengerjaan |
| description | text | ✅ | Deskripsi layanan |
| is_active | boolean | ❌ | Default: `true`. False = tidak bisa dipesan |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |

**Cara hitung harga berdasarkan `pricing_model`:**
| Model | Formula |
|-------|---------|
| `per_kg` | `weight_kg × unit_price` |
| `per_item` | `qty × unit_price` |
| `flat` | `unit_price` (tetap) |

**Contoh data seeder:**
| id | code | name | pricing_model | unit_price |
|----|------|------|---------------|------------|
| 1 | SVC000001 | Cuci Kiloan | per_kg | 7000 |
| 2 | SVC000002 | Setrika Saja | per_kg | 5000 |
| 3 | SVC000003 | Dry Clean Jas | per_item | 25000 |

---

## Tabel `orders`

Header transaksi order. Satu baris = satu nota laundry.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| order_number | varchar(30) UNIQUE | ❌ | Format: `ORD260315093045` |
| customer_id | FK → customers.id | ❌ | Cascade delete |
| received_by_user_id | FK → users.id | ✅ | Null on delete. Staff yang menerima |
| outlet_name | varchar(255) | ✅ | Nama outlet kalau multi-outlet |
| order_date | datetime | ❌ | Waktu order masuk |
| estimated_done_at | datetime | ✅ | Estimasi selesai |
| completed_at | datetime | ✅ | Waktu selesai aktual |
| status | varchar(255) | ❌ | Default: `pending` |
| payment_status | enum | ❌ | Default: `unpaid` |
| subtotal | decimal(15,2) | ❌ | Total sebelum diskon/pajak. Default: 0 |
| discount_amount | decimal(15,2) | ❌ | Potongan harga. Default: 0 |
| tax_amount | decimal(15,2) | ❌ | Pajak. Default: 0 |
| extra_charge_amount | decimal(15,2) | ❌ | Biaya tambahan. Default: 0 |
| total_amount | decimal(15,2) | ❌ | = subtotal - discount + tax + extra. Default: 0 |
| amount_paid | decimal(15,2) | ❌ | Sudah dibayar. Default: 0 |
| amount_due | decimal(15,2) | ❌ | Sisa hutang = total - paid. Default: 0 |
| notes | text | ✅ | Catatan order |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |

**Alur status order:**
```
pending → received → washing → drying → ironing → ready → delivered → completed
                                                                    ↘ cancelled
```

**Nilai `payment_status`:**
| Nilai | Keterangan |
|-------|-----------|
| `unpaid` | Belum bayar sama sekali |
| `partial` | Bayar sebagian (DP) |
| `paid` | Lunas |
| `refunded` | Dana dikembalikan |

---

## Tabel `order_items`

Detail item/layanan dalam satu order. Satu order bisa punya banyak item.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| order_id | FK → orders.id | ❌ | Cascade delete |
| service_id | FK → services.id | ❌ | Cascade delete |
| service_type | enum | ❌ | `kiloan` atau `per_item` |
| item_name | varchar(255) | ✅ | Snapshot nama layanan saat order |
| qty | integer | ❌ | Default: 1 |
| weight_kg | decimal(8,2) | ✅ | Wajib diisi jika `service_type = kiloan` |
| unit_price | decimal(12,2) | ❌ | Snapshot harga saat order |
| line_total | decimal(12,2) | ❌ | Total harga item ini |
| notes | text | ✅ | Catatan per item |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |

**Kenapa `item_name` dan `unit_price` disimpan sebagai snapshot?**
Karena harga dan nama layanan bisa berubah kapan saja. Dengan menyimpan snapshot, histori harga order tetap akurat walau `services` diubah kemudian.

**Mapping `pricing_model` → `service_type`:**
| pricing_model (services) | service_type (order_items) |
|--------------------------|---------------------------|
| `per_kg` | `kiloan` |
| `per_item` | `per_item` |
| `flat` | `per_item` |

---

## Tabel `payments`

Setiap transaksi pembayaran untuk sebuah order.
Satu order bisa punya banyak payment (DP → pelunasan).

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| order_id | FK → orders.id | ❌ | Cascade delete |
| payment_number | varchar(30) UNIQUE | ❌ | Format: `PAY260315001` |
| payment_date | datetime | ❌ | Waktu transaksi terjadi |
| method | enum | ❌ | `cash`, `transfer`, `qris`, `ewallet` |
| amount | decimal(15,2) | ❌ | Jumlah yang dibayar di transaksi ini |
| paid_by_user_id | FK → users.id | ✅ | Null on delete. Staff yang menerima bayaran |
| reference_no | varchar(100) | ✅ | Nomor referensi bank/e-wallet |
| notes | text | ✅ | Catatan pembayaran |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |

---

## Tabel `deliveries`

Jadwal dan status pickup/delivery untuk setiap order.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint PK | ❌ | Auto increment |
| order_id | FK → orders.id | ❌ | Cascade delete |
| type | varchar | ❌ | `pickup` atau `delivery` |
| address | text | ❌ | Alamat pickup/delivery |
| scheduled_at | timestamp | ✅ | Jadwal rencana |
| completed_at | timestamp | ✅ | Waktu selesai aktual |
| courier_user_id | FK → users.id | ✅ | Null on delete. Bisa null jika belum di-assign |
| status | varchar | ❌ | Default: `pending` |
| notes | text | ✅ | Catatan untuk kurir |
| created_at | timestamp | ✅ | — |
| updated_at | timestamp | ✅ | — |

**Nilai `type`:**
- `pickup` — kurir jemput pakaian ke alamat pelanggan
- `delivery` — kurir antar pakaian selesai ke pelanggan

**Nilai `status`:**
`pending` → `on_the_way` → `done` / `cancelled`

---

## Tabel `sessions`

Dikelola otomatis oleh Laravel untuk menyimpan session login.
Jangan diubah manual.

---

## Tabel yang Belum Dibuat (Rencana Fase 2)

| Tabel | Fungsi |
|-------|--------|
| `order_status_histories` | Riwayat perubahan status order (audit trail) |
| `expenses` | Pencatatan pengeluaran untuk laporan laba rugi |

---

## Catatan Migrasi ke MySQL

Saat project siap pindah dari SQLite ke MySQL di production:

1. Update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laundry_lastri
DB_USERNAME=root
DB_PASSWORD=password_kamu
```

2. Buat database di MySQL:
```sql
CREATE DATABASE laundry_lastri CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Jalankan ulang migration:
```bash
php artisan migrate:fresh --seed
```
