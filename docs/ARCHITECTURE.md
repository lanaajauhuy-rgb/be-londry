# Arsitektur Project — Laundry Lastri

Penjelasan struktur folder, alur request, dan keputusan desain arsitektur backend.

---

## Struktur Folder

```
londry-lastri/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # Terima request, panggil service, balas JSON
│   │   │   ├── AuthController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── OrderController.php
│   │   │   ├── OrderItemController.php
│   │   │   ├── PublicOrderController.php
│   │   │   ├── SeoController.php
│   │   │   ├── ServiceController.php
│   │   │   └── SitemapController.php
│   │   └── Middleware/
│   │       └── AdminMiddleware.php  # Cek role admin sebelum masuk controller
│   ├── Models/                  # Representasi tabel database
│   │   ├── Customer.php
│   │   ├── Delivery.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Service.php
│   │   └── User.php
│   └── Services/                # Logic bisnis yang lebih kompleks
│       └── SeoService.php
│
├── database/
│   ├── migrations/              # Definisi struktur tabel
│   └── seeders/                 # Data awal (admin, layanan contoh, dll)
│
├── docs/                        # Dokumentasi project (file ini)
│
├── public/
│   ├── index.php                # Entry point semua request
│   ├── robots.txt               # File statis SEO
│   └── .htaccess                # Konfigurasi Apache (routing & sitemap)
│
└── routes/
    ├── api.php                  # Semua route /api/v1/*
    └── web.php                  # Route web: /, sitemap.xml
```

---

## Alur Request

### Request API biasa (admin)

```
Client (Postman / Frontend)
    │
    ▼
Apache / Nginx
    │
    ▼
public/index.php          ← entry point Laravel
    │
    ▼
routes/api.php            ← cocokkan URL dengan route
    │
    ▼
Middleware Stack:
  1. EncryptCookies
  2. AddQueuedCookiesToResponse
  3. StartSession
  4. SubstituteBindings
  [5. auth]               ← cek apakah sudah login
  [6. admin]              ← cek apakah role = admin (AdminMiddleware)
    │
    ▼
Controller               ← validasi input, panggil model, balas JSON
    │
    ▼
Model (Eloquent)         ← query ke database
    │
    ▼
Database (SQLite)
    │
    ▼ (balik ke client)
JSON Response
```

### Request Public Order (pelanggan)

```
Pelanggan (form di website)
    │
    ▼
POST /api/v1/public/orders
    │  (tidak perlu login)
    ▼
PublicOrderController::store()
    │
    ▼
DB::transaction() {
  1. Cari/buat Customer by phone
  2. Hitung subtotal dari items
  3. Buat Order
  4. Buat OrderItems
  5. Buat Delivery (pickup)
}
    │
    ▼
JSON Response (order_number, total_amount, dll)
```

---

## Layer Arsitektur

### Controller (tipis)
Controller harus **tipis** — tidak berisi logic bisnis. Tugasnya hanya:
1. Terima request
2. Validasi input
3. Panggil Model atau Service
4. Balas JSON

Contoh controller yang tipis (CustomerController):
```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...]);
    $customer = Customer::create($validated);
    return response()->json(['data' => $customer], 201);
}
```

### Service Layer
Dipakai kalau logic terlalu kompleks untuk controller.
Contoh: `SeoService` yang mengelola meta tags dan JSON-LD untuk berbagai halaman.

### Model (Eloquent ORM)
Setiap model merepresentasikan satu tabel.
Model berisi `$fillable` (proteksi mass assignment) dan relasi antar tabel (`hasMany`, `belongsTo`).

---

## Keputusan Desain

### Kenapa session-based auth (bukan token/Sanctum)?

Project ini masih fase awal dan target utama adalah admin panel web.
Session lebih sederhana untuk setup awal — tidak perlu kelola token secara manual.
Kalau nanti butuh mobile app atau multiple client, bisa migrasi ke Sanctum token.

### Kenapa SQLite untuk development?

- Zero configuration — tidak perlu install MySQL/PostgreSQL terpisah
- File tunggal (`database.sqlite`) — mudah di-share, di-reset, dan di-backup
- Kompatibel dengan semua migration Laravel
- Saat production, cukup ubah `DB_CONNECTION` di `.env` ke MySQL

### Kenapa `PublicOrderController` terpisah dari `OrderController`?

Karena alurnya sangat berbeda:
- `PublicOrderController` untuk pelanggan — tidak perlu login, auto-create customer, auto-hitung harga, auto-generate order number
- `OrderController` untuk admin — buat/edit order secara manual, semua field diisi sendiri

Menggabungkan keduanya akan membuat controller jadi gemuk dan susah di-maintain.

### Kenapa snapshot harga di `order_items`?

Kolom `unit_price` dan `item_name` di tabel `order_items` menyimpan nilai saat order dibuat, bukan foreign key ke harga saat ini.

Alasannya: kalau harga layanan naik bulan depan, histori order bulan ini harus tetap menunjukkan harga lama. Kalau hanya simpan `service_id` tanpa snapshot, semua histori order akan ikut berubah saat harga diubah.

### Kenapa `DB::transaction()` di PublicOrderController?

Karena satu request membuat 3–4 record sekaligus (Customer/update, Order, OrderItems, Delivery).
Kalau salah satu gagal (misalnya OrderItem gagal karena validasi), semua record sebelumnya harus dibatalkan.
Tanpa transaction, data bisa setengah-setengah (Order ada tapi OrderItem tidak ada).

---

## Middleware yang Dipakai

| Middleware | Lokasi | Fungsi |
|-----------|--------|--------|
| `EncryptCookies` | Laravel built-in | Enkripsi cookie request/response |
| `AddQueuedCookiesToResponse` | Laravel built-in | Lampirkan cookie yang di-queue ke response |
| `StartSession` | Laravel built-in | Aktifkan session untuk setiap request |
| `SubstituteBindings` | Laravel built-in | Route Model Binding (ID di URL → object) |
| `auth` | Laravel built-in | Cek apakah user sudah login |
| `admin` | `AdminMiddleware` | Cek apakah user punya role `admin` |

---

## Route Model Binding

Laravel secara otomatis mengkonversi parameter ID di URL menjadi object Eloquent.

Contoh di route: `GET /api/v1/customers/{customer}`
Laravel otomatis:
1. Ambil nilai `{customer}` dari URL (misal: `5`)
2. Jalankan `Customer::findOrFail(5)`
3. Inject hasilnya sebagai parameter `$customer` di controller
4. Kalau tidak ketemu → otomatis balas 404

Ini yang memungkinkan controller ditulis ringkas seperti:
```php
public function show(Customer $customer): JsonResponse
{
    return response()->json(['data' => $customer]);
}
```
Tanpa perlu tulis `Customer::findOrFail($id)` manual.
