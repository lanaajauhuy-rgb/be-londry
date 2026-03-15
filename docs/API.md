# API Reference — Laundry Lastri

Dokumentasi lengkap semua endpoint REST API backend Laundry Lastri.

---

## Base URL

```
Development : http://londry-lastri.test/api/v1
Production  : https://domain-kamu.com/api/v1
```

---

## Autentikasi

API ini menggunakan **session-based authentication** (bukan token/Bearer).

Setelah login berhasil, Laravel menyimpan sesi di cookie. Setiap request berikutnya harus menyertakan cookie tersebut agar dikenali sebagai user yang sudah login.

Untuk request dari Postman atau curl, tambahkan header:
```
Accept: application/json
Content-Type: application/json
```

---

## Format Response

### Sukses
```json
{
  "message": "Deskripsi hasil",
  "data": { ... }
}
```

### Error Validasi (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "nama_field": ["Pesan error pertama.", "Pesan error kedua."]
  }
}
```

### Error Auth (401)
```json
{ "message": "Unauthenticated" }
```

### Error Akses (403)
```json
{ "message": "Forbidden. Hanya admin yang boleh mengakses resource ini." }
```

---

## Ringkasan Endpoint

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| POST | `/register` | ❌ | Daftar akun admin baru |
| POST | `/login` | ❌ | Login dan buat session |
| POST | `/logout` | ✅ | Logout dan hapus session |
| POST | `/public/orders` | ❌ | Buat order dari pelanggan umum |
| GET | `/customers` | ✅ Admin | Daftar semua customer |
| POST | `/customers` | ✅ Admin | Tambah customer baru |
| GET | `/customers/{id}` | ✅ Admin | Detail satu customer |
| PUT | `/customers/{id}` | ✅ Admin | Update customer |
| DELETE | `/customers/{id}` | ✅ Admin | Hapus customer |
| GET | `/services` | ✅ Admin | Daftar semua layanan |
| POST | `/services` | ✅ Admin | Tambah layanan baru |
| GET | `/services/{id}` | ✅ Admin | Detail satu layanan |
| PUT | `/services/{id}` | ✅ Admin | Update layanan |
| DELETE | `/services/{id}` | ✅ Admin | Hapus layanan |
| GET | `/orders` | ✅ Admin | Daftar semua order |
| POST | `/orders` | ✅ Admin | Buat order manual |
| GET | `/orders/{id}` | ✅ Admin | Detail satu order |
| PUT | `/orders/{id}` | ✅ Admin | Update order |
| DELETE | `/orders/{id}` | ✅ Admin | Hapus order |
| GET | `/order-items` | ✅ Admin | Daftar semua item order |
| POST | `/order-items` | ✅ Admin | Tambah item ke order |
| GET | `/order-items/{id}` | ✅ Admin | Detail satu item |
| PUT | `/order-items/{id}` | ✅ Admin | Update item |
| DELETE | `/order-items/{id}` | ✅ Admin | Hapus item |
| GET | `/seo/meta/{page}` | ❌ | Meta tags untuk halaman |
| GET | `/seo/schema/{page}` | ❌ | JSON-LD schema untuk halaman |

---

## Auth

### POST `/register`

Daftarkan akun admin baru.

**Request Body:**
```json
{
  "name": "Nama Admin",
  "email": "admin@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "08123456789"
}
```

**Field:**
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| name | string | ✅ | Maksimal 255 karakter |
| email | string | ✅ | Format email valid, harus unik |
| password | string | ✅ | Minimal 8 karakter |
| password_confirmation | string | ✅ | Harus sama dengan password |
| phone | string | ❌ | Maksimal 20 karakter |

**Response (201):**
```json
{
  "message": "Register berhasil",
  "data": {
    "id": 1,
    "name": "Nama Admin",
    "email": "admin@example.com",
    "role": "admin",
    "is_active": true,
    "created_at": "2026-03-15T10:00:00.000000Z"
  }
}
```

---

### POST `/login`

Login dan buat session.

**Request Body:**
```json
{
  "email": "lananuranf@gmail.com",
  "password": "lana@121212"
}
```

**Response (200):**
```json
{
  "message": "Login berhasil",
  "data": {
    "id": 1,
    "name": "Super Admin",
    "email": "lananuranf@gmail.com",
    "role": "admin",
    "is_active": true,
    "last_login_at": "2026-03-15T10:00:00.000000Z"
  }
}
```

**Response (401) — email/password salah:**
```json
{ "message": "Email atau password salah" }
```

**Response (403) — akun nonaktif:**
```json
{ "message": "Akun tidak aktif" }
```

---

### POST `/logout`

Logout dan hapus session. Membutuhkan login.

**Response (200):**
```json
{ "message": "Logout berhasil" }
```

---

## Public Order

### POST `/public/orders`

Buat order baru dari pelanggan umum tanpa perlu login.
Endpoint ini untuk form order di website yang diakses customer.

**Request Body:**
```json
{
  "customer": {
    "name": "Budi Santoso",
    "phone": "081234567890",
    "email": "budi@email.com",
    "address": "Jl. Melati No. 10, Jakarta",
    "notes": "Pakaian putih jangan dicampur"
  },
  "pickup": {
    "address": "Jl. Melati No. 10, Jakarta",
    "scheduled_at": "2026-03-16 09:00:00",
    "notes": "Hubungi dulu sebelum datang"
  },
  "items": [
    {
      "service_id": 1,
      "qty": 1,
      "weight_kg": 3.5,
      "notes": "Pisahkan baju putih"
    },
    {
      "service_id": 3,
      "qty": 2,
      "notes": null
    }
  ]
}
```

**Field `customer`:**
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| name | string | ✅ | Nama lengkap pelanggan |
| phone | string | ✅ | Nomor HP, digunakan sebagai identitas unik |
| email | string | ❌ | Email pelanggan |
| address | string | ✅ | Alamat pelanggan |
| notes | string | ❌ | Catatan tambahan pelanggan |

**Field `pickup`:**
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| address | string | ✅ | Alamat pickup |
| scheduled_at | datetime | ❌ | Jadwal pickup |
| notes | string | ❌ | Catatan untuk kurir |

**Field `items[]`:**
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| service_id | integer | ✅ | ID layanan (harus ada di tabel services) |
| qty | integer | ✅ | Jumlah item (min: 1) |
| weight_kg | decimal | Kondisional | Wajib jika layanan `per_kg` |
| notes | string | ❌ | Catatan per item |

> **Catatan:** Kalau `service_id` merujuk ke layanan dengan `pricing_model = per_kg`, maka `weight_kg` wajib diisi.

**Alur pemrosesan:**
1. Cari customer berdasarkan nomor HP
2. Kalau sudah pernah order → update datanya
3. Kalau baru → buat customer baru dengan kode otomatis (`CUS00001`)
4. Hitung subtotal dari semua items
5. Buat record order dengan status `pending` dan payment `unpaid`
6. Buat semua order items
7. Buat record delivery untuk pickup

**Response (201):**
```json
{
  "message": "Order berhasil dibuat",
  "data": {
    "order_id": 15,
    "order_number": "ORD260316090000",
    "customer_id": 8,
    "status": "pending",
    "payment_status": "unpaid",
    "total_amount": "49500.00"
  }
}
```

---

## Customers

Semua endpoint customer membutuhkan login admin.

### GET `/customers`

Ambil daftar semua customer, diurutkan dari yang terbaru.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "customer_code": "CUS00001",
      "name": "Budi Santoso",
      "phone": "081234567890",
      "email": "budi@email.com",
      "address": "Jl. Melati No. 10",
      "notes": null,
      "created_at": "2026-03-14T10:00:00.000000Z",
      "updated_at": "2026-03-14T10:00:00.000000Z"
    }
  ]
}
```

---

### POST `/customers`

Tambah customer baru secara manual (oleh admin).

**Request Body:**
```json
{
  "customer_code": "CUS00010",
  "name": "Siti Rahayu",
  "phone": "087812345678",
  "email": "siti@email.com",
  "address": "Jl. Anggrek No. 5, Bandung",
  "notes": "Pelanggan tetap"
}
```

**Field:**
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| customer_code | string | ✅ | Maks 10 karakter, harus unik |
| name | string | ✅ | Maks 50 karakter |
| phone | string | ✅ | Maks 13 karakter |
| email | string | ❌ | Format email valid |
| address | string | ✅ | Maks 500 karakter |
| notes | string | ❌ | Maks 255 karakter |

**Response (201):**
```json
{
  "message": "Customer berhasil dibuat",
  "data": { ... }
}
```

---

### GET `/customers/{id}`

Detail satu customer.

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "customer_code": "CUS00001",
    ...
  }
}
```

**Response (404):** Customer tidak ditemukan.

---

### PUT `/customers/{id}`

Update data customer.

**Request Body:** Sama seperti POST, tapi `customer_code` boleh sama dengan yang lama.

**Response (200):**
```json
{
  "message": "Customer berhasil diupdate",
  "data": { ... }
}
```

---

### DELETE `/customers/{id}`

Hapus customer. Data terhapus permanen.

**Response (200):**
```json
{ "message": "Customer berhasil dihapus" }
```

---

## Services

Semua endpoint service membutuhkan login admin.

### GET `/services`

Daftar semua layanan laundry.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "code": "SVC000001",
      "name": "Cuci Kiloan",
      "pricing_model": "per_kg",
      "unit_price": "7000.00",
      "estimated_hours": 48,
      "description": "Layanan cuci kiloan reguler.",
      "is_active": true,
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

---

### POST `/services`

Tambah layanan baru.

**Request Body:**
```json
{
  "code": "SVC000004",
  "name": "Express Wash",
  "pricing_model": "per_kg",
  "unit_price": 15000,
  "estimated_hours": 6,
  "description": "Cuci kiloan selesai dalam 6 jam",
  "is_active": true
}
```

**Field:**
| Field | Tipe | Wajib | Nilai Valid |
|-------|------|-------|------------|
| code | string | ✅ | Maks 7 karakter, unik |
| name | string | ✅ | Maks 50 karakter |
| pricing_model | string | ✅ | `per_kg`, `per_item`, `flat` |
| unit_price | decimal | ✅ | Min 0 |
| estimated_hours | integer | ❌ | Min 0 |
| description | string | ❌ | — |
| is_active | boolean | ✅ | `true` atau `false` |

---

### GET/PUT/DELETE `/services/{id}`

Sama dengan pola Customer di atas.

---

## Orders

Semua endpoint order membutuhkan login admin.

### GET `/orders`

Daftar semua order, diurutkan dari yang terbaru.

### POST `/orders`

Buat order baru secara manual (admin membuat order langsung, bukan dari form publik).

**Request Body:**
```json
{
  "order_number": "ORD260315001",
  "customer_id": 1,
  "received_by_user_id": 1,
  "outlet_name": null,
  "order_date": "2026-03-15 09:00:00",
  "estimated_done_at": "2026-03-17 17:00:00",
  "completed_at": null,
  "status": "pending",
  "payment_status": "unpaid",
  "subtotal": 35000,
  "total_amount": 35000,
  "amount_paid": 0,
  "amount_due": 35000,
  "notes": "Laundry biasa"
}
```

**Field `status` yang disarankan:**
`pending` → `received` → `washing` → `drying` → `ironing` → `ready` → `delivered` → `completed` / `cancelled`

**Field `payment_status`:**
`unpaid` | `partial` | `paid` | `refunded`

---

### GET/PUT/DELETE `/orders/{id}`

Sama dengan pola Customer.

> **Perhatian:** Menghapus order akan otomatis menghapus semua `order_items` dan `deliveries` yang berelasi (cascade delete).

---

## Order Items

### POST `/order-items`

Tambah item ke order yang sudah ada.

**Request Body:**
```json
{
  "order_id": 1,
  "service_id": 1,
  "service_type": "kiloan",
  "item_name": "Cuci Kiloan",
  "qty": 1,
  "weight_kg": 3.5,
  "unit_price": 7000,
  "line_total": 24500,
  "notes": null
}
```

**Field `service_type`:**
| Nilai | Dipakai untuk pricing_model |
|-------|---------------------------|
| `kiloan` | `per_kg` |
| `per_item` | `per_item` atau `flat` |

> **Penting:** Kalau `service_type = kiloan`, maka `weight_kg` wajib diisi dan lebih dari 0.

---

## SEO API

Endpoint publik untuk digunakan frontend saat render halaman.

### GET `/seo/meta/{page}`

Ambil meta tags untuk halaman tertentu.

**Nilai `{page}` yang didukung:**
| Nilai | Keterangan |
|-------|-----------|
| `home` | Halaman beranda |
| `services` | Halaman daftar layanan |
| `order` | Halaman form order |
| `services.{id}` | Detail layanan (contoh: `services.1`) |

**Contoh:**
```bash
curl http://londry-lastri.test/api/v1/seo/meta/home
curl http://londry-lastri.test/api/v1/seo/meta/services.1
```

**Response (200):**
```json
{
  "success": true,
  "page": "home",
  "data": {
    "title": "Laundry Lastri — Jasa Laundry Kiloan Terpercaya",
    "description": "Laundry Lastri — jasa laundry kiloan...",
    "keywords": "laundry kiloan, jasa laundry, ...",
    "canonical": "https://laundrylastri.com/",
    "og_url": "https://laundrylastri.com/",
    "og_type": "website",
    "og_image": "https://laundrylastri.com/images/og-default.jpg",
    "twitter_card": "summary_large_image"
  }
}
```

---

### GET `/seo/schema/{page}`

Ambil JSON-LD structured data untuk halaman tertentu.

**Nilai `{page}`:** Sama seperti `/seo/meta/{page}`.

**Response (200) — `home`:**
```json
{
  "success": true,
  "page": "home",
  "data": {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "Laundry Lastri",
    "description": "...",
    "url": "https://laundrylastri.com",
    "telephone": "+62-XXX-XXXX-XXXX",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Jl. Contoh No. 1",
      "addressLocality": "Jakarta",
      "addressCountry": "ID"
    },
    "openingHoursSpecification": [ ... ]
  }
}
```

**Cara pakai di frontend (Next.js/React):**
```jsx
// Fetch saat server-side render
const schema = await fetch('/api/v1/seo/schema/home').then(r => r.json());

// Inject ke <head>
<script
  type="application/ld+json"
  dangerouslySetInnerHTML={{ __html: JSON.stringify(schema.data) }}
/>
```

---

## File Statis SEO

### GET `/robots.txt`

Dilayani langsung dari `public/robots.txt` (file statis).

```
User-agent: *
Allow: /

Disallow: /admin/
Disallow: /api/

Sitemap: https://laundrylastri.com/sitemap.xml
```

### GET `/sitemap.xml`

Dihasilkan secara dinamis dari database oleh `SitemapController`.
Berisi semua URL halaman + URL detail setiap layanan aktif.

```bash
curl http://londry-lastri.test/sitemap.xml
```
