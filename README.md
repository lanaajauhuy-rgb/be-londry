# Laundry Lastri — Backend API

REST API backend untuk sistem manajemen laundry. Production-ready, token-based auth, real-time order tracking.

---

## Stack

| Komponen | Teknologi |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.2 |
| Database | SQLite (dev) / MySQL (prod) |
| Auth | Laravel Sanctum (Bearer Token) |
| API Style | REST API v1 |

---

## Cara Install

```bash
# 1. Clone
git clone https://github.com/username/londry-lastri.git
cd londry-lastri

# 2. Install dependencies
composer install

# 3. Setup environment
copy .env.example .env
php artisan key:generate

# 4. Database
touch database/database.sqlite
php artisan migrate --seed

# 5. Jalankan
php artisan serve
```

API tersedia di: `http://127.0.0.1:8000/api/v1`

---

## Autentikasi

Semua endpoint (kecuali `/login`, `/register`, `/public/orders`) membutuhkan token.

**Login untuk mendapatkan token:**
```http
POST /api/v1/login
Content-Type: application/json

{ "email": "admin@laundry.com", "password": "password" }
```

**Respons:**
```json
{ "token": "1|AbCdEfGhIj...", "data": { "id": 1, "name": "Admin" } }
```

**Gunakan token di setiap request:**
```
Authorization: Bearer 1|AbCdEfGhIj...
Accept: application/json
```

**Token behavior:**
- Sliding expiry: token expire setelah **30 menit tidak aktif**
- Setiap request yang berhasil → timer reset otomatis
- Satu user = satu token aktif (login baru = token lama hangus)

---

## Endpoint Lengkap

### Auth

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| `POST` | `/register` | ❌ | Daftarkan admin baru, langsung dapat token |
| `POST` | `/login` | ❌ | Login, dapat token |
| `POST` | `/logout` | ✅ | Hapus token aktif |
| `GET` | `/me` | ✅ | Data user yang sedang login |

---

### Customers

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/customers` | List semua customer (search + pagination) |
| `POST` | `/customers` | Tambah customer baru |
| `GET` | `/customers/{id}` | Detail customer + statistik + 5 order terbaru |
| `PUT` | `/customers/{id}` | Update customer |
| `DELETE` | `/customers/{id}` | Hapus customer (tidak bisa jika ada order aktif) |
| `GET` | `/customers/{id}/orders` | Riwayat semua order customer ini |

**Query params untuk `GET /customers`:**
```
?search=budi        → cari by nama, phone, atau customer_code
?per_page=20        → jumlah per halaman (default: 15)
?page=2             → halaman ke-2
```

---

### Services (Layanan)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/services` | List semua layanan |
| `POST` | `/services` | Tambah layanan baru |
| `GET` | `/services/{id}` | Detail layanan |
| `PUT` | `/services/{id}` | Update layanan |
| `DELETE` | `/services/{id}` | Hapus layanan |

**Pricing model yang didukung:**
- `per_kg` — dihitung per kilogram
- `per_item` — dihitung per satuan
- `flat` — harga tetap

---

### Orders

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/orders` | List order (filter + search + pagination) |
| `POST` | `/orders` | Buat order baru (admin) |
| `GET` | `/orders/{id}` | Detail lengkap: items, payments, status history, delivery |
| `PUT` | `/orders/{id}` | Update order |
| `DELETE` | `/orders/{id}` | Hapus order (cascade ke items & deliveries) |

**Query params untuk `GET /orders`:**
```
?search=ORD001               → cari by nomor order
?status=washing              → filter by status
?payment_status=unpaid       → filter by status pembayaran
?customer_id=3               → filter by customer
?per_page=20                 → jumlah per halaman
```

**Status order yang valid:**
```
pending → received → washing → drying → ironing → ready → delivered → completed
                                                              ↘ cancelled
```

**Kalkulasi harga otomatis (server-side):**
```
total_amount = subtotal - discount_amount + tax_amount + extra_charge_amount
amount_due   = total_amount - amount_paid
```

---

### Order Items

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/order-items` | List semua item |
| `POST` | `/order-items` | Tambah item ke order |
| `GET` | `/order-items/{id}` | Detail item |
| `PUT` | `/order-items/{id}` | Update item |
| `DELETE` | `/order-items/{id}` | Hapus item |

---

### Payments (nested di bawah orders)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/orders/{order}/payments` | List semua pembayaran order ini |
| `POST` | `/orders/{order}/payments` | Catat pembayaran baru |
| `GET` | `/orders/{order}/payments/{payment}` | Detail pembayaran |
| `DELETE` | `/orders/{order}/payments/{payment}` | Hapus pembayaran (rollback saldo otomatis) |

**Contoh body `POST /orders/{order}/payments`:**
```json
{
    "method": "cash",
    "amount": 50000,
    "payment_date": "2026-03-16 10:00:00",
    "reference_no": null,
    "notes": "DP pertama"
}
```

**Metode pembayaran yang didukung:** `cash`, `transfer`, `qris`, `ewallet`

**Otomatisasi saat payment berhasil:**
- `amount_paid` di order bertambah
- `amount_due` di order berkurang
- `payment_status` otomatis berubah: `unpaid` → `partial` → `paid`

---

### Order Status History (nested di bawah orders)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/orders/{order}/statuses` | Timeline semua perubahan status |
| `POST` | `/orders/{order}/statuses` | Ubah status order + catat history |
| `GET` | `/orders/{order}/statuses/{id}` | Detail satu record history |

> History status bersifat **permanen** — tidak bisa diedit atau dihapus (audit trail).

---

### Deliveries (nested di bawah orders)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/orders/{order}/deliveries` | List semua pickup/delivery order ini |
| `POST` | `/orders/{order}/deliveries` | Jadwalkan pickup atau delivery |
| `GET` | `/orders/{order}/deliveries/{delivery}` | Detail delivery |
| `PUT` | `/orders/{order}/deliveries/{delivery}` | Update: assign kurir, ubah status |
| `DELETE` | `/orders/{order}/deliveries/{delivery}` | Hapus jadwal (tidak bisa jika sudah done) |

**Status delivery:** `pending` → `on_the_way` → `done` / `cancelled`

---

### Users (Staff Management)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/users` | List semua staff (search + filter role) |
| `POST` | `/users` | Buat akun staff baru |
| `GET` | `/users/{id}` | Detail user |
| `PUT` | `/users/{id}` | Update user (password opsional) |
| `PATCH` | `/users/{id}/toggle-active` | Aktifkan / nonaktifkan akun |
| `DELETE` | `/users/{id}` | Hapus akun (proteksi: tidak bisa hapus diri sendiri) |

**Role yang tersedia:** `admin`, `cashier`, `operator`, `courier`

---

### Export

| Endpoint | Format | Deskripsi |
|---|---|---|
| `GET /export/orders/csv` | CSV | Download order ke Excel |
| `GET /export/payments/csv` | CSV | Download pembayaran ke Excel |
| `GET /export/revenue/print` | HTML | Laporan omzet siap cetak PDF |

**Query params export:**
```
?date_from=2026-03-01&date_to=2026-03-31
?status=completed              (khusus orders)
?payment_status=paid           (khusus orders)
?method=cash                   (khusus payments)
```

**Cara export PDF:** Buka URL `/export/revenue/print` di browser → `Ctrl+P` → `Save as PDF`

---

### Reports (Dashboard)

Semua endpoint report bersifat **read-only** (`GET`).

| Endpoint | Deskripsi |
|---|---|
| `GET /reports/summary` | Ringkasan dashboard: omzet hari ini, bulan ini, order pending, order unpaid, top services |
| `GET /reports/orders/daily` | Order & omzet untuk tanggal tertentu |
| `GET /reports/revenue` | Omzet per periode dengan breakdown harian |
| `GET /reports/orders/pending` | Order yang masih dalam proses |
| `GET /reports/orders/unpaid` | Order yang masih ada tagihan + total piutang |
| `GET /reports/services/top` | Layanan terlaris |

**Query params:**
```
GET /reports/orders/daily?date=2026-03-16
GET /reports/revenue?period=weekly          (daily | weekly | monthly)
GET /reports/orders/pending?status=washing&per_page=20
GET /reports/services/top?limit=5
```

---

### Public (tanpa login)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `POST` | `/public/orders` | Pelanggan buat order baru |

**Contoh body:**
```json
{
    "customer": {
        "name": "Siti Rahayu",
        "phone": "081234567890",
        "email": "siti@email.com",
        "address": "Jl. Melati No. 10"
    },
    "pickup": {
        "address": "Jl. Melati No. 10",
        "scheduled_at": "2026-03-16 09:00:00",
        "notes": "Pagi sebelum jam 10"
    },
    "items": [
        { "service_id": 1, "qty": 1, "weight_kg": 3.5 },
        { "service_id": 3, "qty": 2 }
    ]
}
```

---

## Struktur Database

```
users                    → admin & staff
customers                → pelanggan laundry
services                 → master layanan (cuci kiloan, setrika, dll)
orders                   → header transaksi
order_items              → detail item per order
payments                 → riwayat pembayaran
order_status_histories   → audit trail perubahan status
deliveries               → jadwal pickup & antar
personal_access_tokens   → Sanctum tokens
```

---

## Format Response

### Success
```json
{
    "data": { ... },
    "message": "Operasi berhasil"
}
```

### Paginated
```json
{
    "current_page": 1,
    "data": [ ... ],
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "next_page_url": "...",
    "prev_page_url": null
}
```

### Error Validasi (422)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "amount": ["The amount must be at least 0.01."]
    }
}
```

### Unauthorized (401)
```json
{
    "message": "Sesi kamu sudah berakhir karena tidak aktif selama 30 menit.",
    "reason": "token_expired_idle",
    "idle_timeout": 30
}
```

---

## Akun Default Seeder

```
Email    : lananuranf@gmail.com
Password : lana@121212
Role     : admin
```

---

## Rate Limiting

| Endpoint | Batas | Kunci |
|---|---|---|
| `POST /login` | 5x / menit | Per IP |
| `POST /register` | 3x / jam | Per IP |
| `POST /public/orders` | 10x / menit | Per IP |
| Semua endpoint admin | 120x / menit | Per user |

Jika limit terlampaui, API return `429 Too Many Requests`.

---

## Notifikasi WhatsApp (Opsional)

Notifikasi dikirim otomatis saat status order berubah ke: `received`, `ready`, `completed`, `cancelled`.

**Cara aktifkan WA via Fonnte:**
1. Daftar di [fonnte.com](https://fonnte.com)
2. Hubungkan nomor WA laundry
3. Copy API token dari dashboard
4. Tambah ke `.env`:
   ```
   FONNTE_TOKEN=your_token_here
   ```
5. Buka `app/Listeners/SendOrderStatusNotification.php`
6. Uncomment baris `$this->sendWhatsApp(...)`
7. Aktifkan queue worker:
   ```bash
   php artisan queue:work
   ```

---

## Catatan Deployment ke Production

```env
APP_ENV=production
APP_DEBUG=false
BCRYPT_ROUNDS=12
SESSION_DRIVER=database
CACHE_STORE=database
DB_CONNECTION=mysql
```

Jangan lupa:
```bash
php artisan config:cache
php artisan route:cache
php artisan optimize
```
