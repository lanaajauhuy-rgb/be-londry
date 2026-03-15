# Panduan Instalasi — Laundry Lastri Backend

Panduan lengkap setup project dari nol di mesin lokal menggunakan Laragon (Windows).

---

## Prasyarat

Pastikan semua tools ini sudah terinstall sebelum mulai:

| Tool | Versi Minimum | Cek |
|------|--------------|-----|
| PHP | 8.2+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 18+ | `node -v` |
| NPM | 9+ | `npm -v` |
| Git | Terbaru | `git -v` |
| Laragon | 6.x | Buka app |

---

## Cara Setup

### 1. Clone atau buka project

Kalau belum ada di lokal:
```bash
cd C:\Users\Lananuranf\laragon\www
git clone <url-repo> londry-lastri
cd londry-lastri
```

Kalau sudah ada:
```bash
cd C:\Users\Lananuranf\laragon\www\londry-lastri
```

---

### 2. Install dependencies PHP

```bash
composer install
```

Kalau muncul error memory limit:
```bash
php -d memory_limit=-1 composer install
```

---

### 3. Buat file environment

```bash
cp .env.example .env
```

Lalu edit `.env` sesuai konfigurasi lokal:

```env
APP_NAME="Laundry Lastri"
APP_ENV=local
APP_KEY=                    # dikosongkan dulu, akan di-generate di langkah 4
APP_DEBUG=true
APP_URL=http://londry-lastri.test

DB_CONNECTION=sqlite        # pakai SQLite untuk development

SESSION_DRIVER=database
```

---

### 4. Generate application key

```bash
php artisan key:generate
```

Perintah ini akan mengisi `APP_KEY` di file `.env` secara otomatis.

---

### 5. Buat database SQLite

Project ini pakai SQLite untuk development. File database sudah ada di:
```
database/database.sqlite
```

Kalau file tidak ada, buat manual:
```bash
touch database/database.sqlite
```

Atau di Windows PowerShell:
```powershell
New-Item database/database.sqlite
```

---

### 6. Jalankan migration

```bash
php artisan migrate
```

Ini akan membuat semua tabel di database. Tabel yang dibuat:
- `users`
- `customers`
- `services`
- `orders`
- `order_items`
- `payments`
- `deliveries`
- `sessions`

---

### 7. Jalankan seeder (data awal)

```bash
php artisan db:seed
```

Seeder yang dijalankan:
- `AdminUserSeeder` — buat akun admin default
- `CustomerSeeder` — buat beberapa customer contoh
- `ServiceSeeder` — buat layanan laundry contoh
- `OrderSeeder` — buat beberapa order contoh
- `OrderItemsSeeder` — buat item untuk order contoh

**Akun admin default (dari seeder):**
```
Email    : lananuranf@gmail.com
Password : lana@121212
```

> Ganti password ini setelah setup selesai!

---

### 8. Install dependencies frontend (opsional)

Kalau mau jalankan frontend assets:
```bash
npm install
npm run build
```

---

### 9. Cek apakah server berjalan

Pastikan Laragon sudah running. Buka browser dan akses:
```
http://londry-lastri.test
```

Harusnya muncul response JSON:
```json
{
  "app": "Laundry Lastri",
  "version": "1.0.0",
  "status": "running"
}
```

---

## Verifikasi Instalasi

Cek satu per satu dengan curl:

```bash
# Health check
curl http://londry-lastri.test

# robots.txt
curl http://londry-lastri.test/robots.txt

# sitemap.xml
curl http://londry-lastri.test/sitemap.xml

# API login
curl -X POST http://londry-lastri.test/api/v1/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"lananuranf@gmail.com\",\"password\":\"lana@121212\"}"
```

---

## Troubleshooting

### Error: Could not connect to server
Laragon belum running. Buka Laragon dan klik **Start All**.

### Error: SQLSTATE[HY000]: no such table
Database belum di-migrate. Jalankan `php artisan migrate`.

### Error: APP_KEY tidak di-set
Jalankan `php artisan key:generate`.

### Virtual host tidak muncul
Pastikan Root directory Laragon menunjuk ke folder yang benar.
Di Laragon → Preferences → General → Root: `C:\Users\Lananuranf\laragon\www`

### Error 500 saat akses API
Cek file log di:
```
storage/logs/laravel.log
```

### Reset database (hapus semua data dan mulai ulang)
```bash
php artisan migrate:fresh --seed
```

> **Hati-hati:** perintah ini menghapus SEMUA data dan membuat ulang dari nol.

---

## Perintah Artisan yang Sering Dipakai

```bash
# Lihat semua route yang terdaftar
php artisan route:list

# Buka shell interaktif untuk eksperimen kode
php artisan tinker

# Clear semua cache
php artisan optimize:clear

# Jalankan ulang migration dari awal + seeder
php artisan migrate:fresh --seed

# Cek status migration
php artisan migrate:status
```
