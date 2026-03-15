# TASK.md

## Project
Backend System Laundry

## Goal
Membangun backend API untuk sistem laundry yang mendukung:
- manajemen pelanggan
- pencatatan order laundry
- detail item/order
- tracking status proses laundry
- pembayaran
- pickup/delivery sederhana
- laporan operasional dasar

---

# 1. Rekomendasi Stack Backend

## Core Stack
- **Framework**: Laravel 12
- **Language**: PHP 8.2
- **Database**: SQLite (fase awal/dev), siap migrasi ke MySQL/PostgreSQL jika scale naik
- **API Style**: REST API
- **Authentication**: Laravel Sanctum
- **Queue**: database
- **Logging**: default Laravel log
- **Validation**: Form Request Laravel
- **Testing**: PHPUnit
- **Code Style**: Laravel Pint

## Kenapa stack ini
- cepat untuk MVP
- cocok untuk CRUD + workflow
- migration dan seeder sangat membantu iterasi
- queue database cukup untuk notifikasi / job ringan
- Sanctum sederhana untuk admin/staff panel

---

# 2. Scope Backend Fase 1

## Aktor
- Admin
- Staff kasir
- Staff operasional
- Kurir (opsional fase 1.5)

## Fitur Utama
1. Authentication backend
2. Master data pelanggan
3. Master layanan laundry
4. Pembuatan order
5. Detail item/order
6. Perhitungan harga
7. Tracking status order
8. Pembayaran
9. Riwayat transaksi
10. Dashboard summary sederhana
11. Pickup dan delivery sederhana
12. Catatan audit/status history

---

# 3. Rekomendasi Modul Backend

## A. Auth & User Management
- login
- logout
- current user profile
- role-based access sederhana

## B. Customer Management
- tambah pelanggan
- edit pelanggan
- lihat daftar pelanggan
- detail pelanggan
- riwayat order pelanggan

## C. Service Management
Contoh layanan:
- cuci kiloan
- cuci setrika
- setrika saja
- dry clean
- ekspres

Data yang disimpan:
- nama layanan
- tipe harga
- satuan harga
- estimasi selesai
- aktif/tidak aktif

## D. Order Management
- buat order
- tambah item/order detail
- hitung subtotal, diskon, pajak, total
- tentukan tanggal masuk, estimasi selesai
- assign staff
- ubah status

## E. Payment Management
- bayar full / partial
- metode pembayaran
- status pembayaran
- bukti/catatan pembayaran

## F. Order Tracking
Status yang disarankan:
- pending
- received
- washing
- drying
- ironing
- ready
- delivered
- completed
- cancelled

## G. Pickup/Delivery
- alamat pickup/delivery
- jadwal pickup
- jadwal delivery
- status pickup/delivery
- catatan kurir

## H. Reporting
- total order harian
- omzet harian/mingguan/bulanan
- order belum selesai
- order belum lunas
- layanan paling sering dipakai

---

# 4. Rekomendasi Database Schema

## Prinsip desain
- pisahkan master data dan transaksi
- simpan histori status agar tracking rapi
- jangan taruh semua data di 1 tabel order
- siapkan field nullable untuk fleksibilitas MVP

---

## 4.1 Tabel users
Digunakan untuk admin/staff backend.

### Kolom
- id
- name
- email
- password
- role (`admin`, `cashier`, `operator`, `courier`)
- phone
- is_active
- last_login_at
- created_at
- updated_at

---

## 4.2 Tabel customers
Data pelanggan laundry.

### Kolom
- id
- customer_code
- name
- phone
- email nullable
- address nullable
- notes nullable
- created_at
- updated_at

### Catatan
- `phone` sebaiknya di-index
- `customer_code` unik, misalnya `CUST-0001`

---

## 4.3 Tabel services
Master layanan laundry.

### Kolom
- id
- code
- name
- pricing_model (`per_kg`, `per_item`, `flat`)
- unit_price
- estimated_hours nullable
- description nullable
- is_active
- created_at
- updated_at

### Contoh
- Cuci Kiloan → `per_kg`
- Setrika Saja → `per_kg`
- Dry Clean Jas → `per_item`

---

## 4.4 Tabel orders
Header transaksi/order.

### Kolom
- id
- order_number
- customer_id
- received_by_user_id
- outlet_name nullable
- order_date
- estimated_done_at nullable
- completed_at nullable
- status
- payment_status (`unpaid`, `partial`, `paid`, `refunded`)
- subtotal
- discount_amount
- tax_amount
- extra_charge_amount
- total_amount
- amount_paid
- amount_due
- notes nullable
- created_at
- updated_at

### Relasi
- belongs to `customers`
- belongs to `users` sebagai penerima order

### Catatan
- `order_number` unik, contoh `ORD-20260314-0001`

---

## 4.5 Tabel order_items
Detail item atau layanan pada order.

### Kolom
- id
- order_id
- service_id
- item_name nullable
- qty
- weight_kg nullable
- unit_price
- line_total
- notes nullable
- created_at
- updated_at

### Fungsi
Menyimpan detail:
- kiloan
- per item
- tambahan charge item tertentu

### Catatan
Jika layanan kiloan:
- `weight_kg` diisi
- `qty` bisa `1`

Jika layanan per item:
- `qty` diisi
- `weight_kg` nullable

---

## 4.6 Tabel payments
Menyimpan transaksi pembayaran.

### Kolom
- id
- order_id
- payment_number
- payment_date
- method (`cash`, `transfer`, `qris`, `ewallet`)
- amount
- paid_by_user_id nullable
- reference_no nullable
- notes nullable
- created_at
- updated_at

### Catatan
- satu order bisa punya banyak pembayaran
- cocok untuk DP / cicilan pelunasan

---

## 4.7 Tabel order_status_histories
Riwayat perubahan status order.

### Kolom
- id
- order_id
- status
- changed_by_user_id nullable
- changed_at
- notes nullable
- created_at
- updated_at

### Kenapa penting
- tracking operasional
- audit trail
- monitoring SLA

---

## 4.8 Tabel deliveries
Untuk pickup/delivery sederhana.

### Kolom
- id
- order_id
- type (`pickup`, `delivery`)
- address
- scheduled_at nullable
- completed_at nullable
- courier_user_id nullable
- status (`pending`, `on_the_way`, `done`, `cancelled`)
- notes nullable
- created_at
- updated_at

---

## 4.9 Tabel expenses (opsional fase 2)
Untuk laporan laba rugi sederhana.

### Kolom
- id
- expense_date
- category
- amount
- description nullable
- created_by_user_id nullable
- created_at
- updated_at

---

# 5. Relasi Antar Tabel

## Relasi inti
- `customers` 1..n `orders`
- `users` 1..n `orders`
- `orders` 1..n `order_items`
- `services` 1..n `order_items`
- `orders` 1..n `payments`
- `orders` 1..n `order_status_histories`
- `orders` 1..n `deliveries`
- `users` 1..n `payments`
- `users` 1..n `order_status_histories`
- `users` 1..n `deliveries`

---

# 6. Diagram Skema Database Sederhana
