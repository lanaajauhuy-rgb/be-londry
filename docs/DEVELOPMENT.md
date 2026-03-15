# Panduan Development — Laundry Lastri

Konvensi kode, workflow pengembangan, dan panduan untuk kontributor.

---

## Konvensi Penamaan

### File & Class

| Jenis | Konvensi | Contoh |
|-------|----------|--------|
| Controller | PascalCase + `Controller` | `CustomerController.php` |
| Model | PascalCase, singular | `OrderItem.php` |
| Migration | snake_case, timestamp prefix | `2026_03_14_create_orders_table.php` |
| Seeder | PascalCase + `Seeder` | `AdminUserSeeder.php` |
| Service | PascalCase + `Service` | `SeoService.php` |
| Middleware | PascalCase + `Middleware` | `AdminMiddleware.php` |

### Database

| Jenis | Konvensi | Contoh |
|-------|----------|--------|
| Nama tabel | snake_case, plural | `order_items` |
| Foreign key | `{model}_id` | `customer_id`, `order_id` |
| Boolean kolom | prefix `is_` | `is_active` |
| Timestamp kolom | suffix `_at` | `created_at`, `completed_at` |

### Route

| Jenis | Konvensi | Contoh |
|-------|----------|--------|
| Resource route | kebab-case, plural | `/order-items` |
| Custom route | kebab-case | `/public/orders` |
| Param | camelCase atau snake_case | `{orderId}` atau `{order_id}` |

---

## Workflow Development

### Membuat fitur baru

Urutan yang direkomendasikan:

```
1. Buat migration         php artisan make:migration create_xxx_table
2. Edit migration         tambah kolom dan constraint
3. Jalankan migration     php artisan migrate
4. Buat Model             php artisan make:model Xxx
5. Tambah $fillable       dan relasi jika perlu
6. Buat Controller        php artisan make:controller XxxController
7. Tambah route           di routes/api.php
8. Test manual            curl atau Postman
```

### Contoh membuat fitur pembayaran (yang belum ada)

```bash
# 1. Buat controller
php artisan make:controller PaymentController

# 2. Tambah ke routes/api.php di dalam group admin
Route::apiResource('payments', PaymentController::class);

# 3. Isi logic di PaymentController mengikuti pola yang sudah ada
```

---

## Struktur Controller (Pola yang Dipakai)

Semua controller mengikuti pola yang sama:

```php
class XxxController extends Controller
{
    // Daftar semua data
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Xxx::latest()->get(),
        ]);
    }

    // Buat baru
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string'],
        ]);

        $xxx = Xxx::create($validated);

        return response()->json([
            'message' => 'Xxx berhasil dibuat',
            'data' => $xxx,
        ], 201);
    }

    // Detail satu
    public function show(Xxx $xxx): JsonResponse
    {
        return response()->json(['data' => $xxx]);
    }

    // Update
    public function update(Request $request, Xxx $xxx): JsonResponse
    {
        $validated = $request->validate([...]);
        $xxx->update($validated);
        return response()->json(['message' => 'Xxx berhasil diupdate', 'data' => $xxx]);
    }

    // Hapus
    public function destroy(Xxx $xxx): JsonResponse
    {
        $xxx->delete();
        return response()->json(['message' => 'Xxx berhasil dihapus']);
    }
}
```

---

## Validasi

Selalu gunakan `$request->validate()` di controller. Jangan validasi manual dengan `if/else`.

```php
// ✅ Benar
$validated = $request->validate([
    'name'     => ['required', 'string', 'max:255'],
    'email'    => ['required', 'email', 'unique:users,email'],
    'status'   => ['required', Rule::in(['active', 'inactive'])],
    'amount'   => ['required', 'numeric', 'min:0'],
    'notes'    => ['nullable', 'string'],
]);

// ❌ Hindari
if (empty($request->name)) {
    return response()->json(['error' => 'name required'], 422);
}
```

**Aturan validasi yang sering dipakai:**

| Aturan | Keterangan |
|--------|-----------|
| `required` | Field wajib ada dan tidak kosong |
| `nullable` | Field boleh null |
| `string` | Harus berupa teks |
| `integer` | Harus bilangan bulat |
| `numeric` | Boleh integer atau decimal |
| `boolean` | Harus true/false/1/0 |
| `email` | Format email valid |
| `date` | Format tanggal valid |
| `min:N` | Nilai minimal N (angka) atau N karakter (string) |
| `max:N` | Nilai maksimal N |
| `unique:tabel,kolom` | Harus unik di tabel.kolom |
| `exists:tabel,kolom` | Harus ada di tabel.kolom |
| `Rule::in([...])` | Harus salah satu dari array |
| `Rule::unique()->ignore($id)` | Unique tapi abaikan baris dengan ID ini |

---

## HTTP Status Code yang Dipakai

| Code | Situasi |
|------|---------|
| `200 OK` | Request berhasil (read, update, delete) |
| `201 Created` | Data baru berhasil dibuat |
| `401 Unauthorized` | Belum login |
| `403 Forbidden` | Sudah login tapi tidak punya akses |
| `404 Not Found` | Data tidak ditemukan (Route Model Binding) |
| `422 Unprocessable Entity` | Validasi gagal |
| `500 Internal Server Error` | Error tak terduga di server |

---

## Perintah Artisan Berguna

```bash
# Lihat semua route + method + middleware
php artisan route:list

# Lihat route dengan filter nama
php artisan route:list --name=customer

# Buka shell interaktif
php artisan tinker

# Buat migration baru
php artisan make:migration add_column_xxx_to_xxx_table

# Buat model + migration sekaligus
php artisan make:model Xxx -m

# Buat controller
php artisan make:controller XxxController

# Jalankan satu seeder saja
php artisan db:seed --class=ServiceSeeder

# Reset DB + jalankan ulang semua migration + seeder
php artisan migrate:fresh --seed

# Clear semua cache (pakai ini kalau ada perubahan config tidak terbaca)
php artisan optimize:clear

# Format kode dengan Laravel Pint
./vendor/bin/pint

# Generate IDE helper (untuk autocomplete di WebStorm/PhpStorm)
php artisan ide-helper:generate
php artisan ide-helper:models --write
```

---

## Debugging

### Lihat log real-time
```bash
tail -f storage/logs/laravel.log
```

### Atau pakai Pail (Laravel built-in)
```bash
php artisan pail
```

### Debug query SQL
```php
// Tambah sementara di controller untuk lihat query yang dijalankan
\DB::listen(function ($query) {
    \Log::info($query->sql, $query->bindings);
});
```

### Tinker untuk test query
```bash
php artisan tinker

# Contoh perintah di tinker:
>>> Customer::all()
>>> Order::where('status', 'pending')->count()
>>> Service::where('is_active', true)->pluck('name')
```

---

## Yang Belum Diimplementasikan (TODO)

Berdasarkan `task.md`, fitur-fitur ini belum ada:

### High Priority
- [ ] `PaymentController` — endpoint CRUD untuk pembayaran
- [ ] Update `Order.payment_status` otomatis setelah payment ditambah
- [ ] `OrderStatusHistoryController` — riwayat perubahan status

### Medium Priority
- [ ] `DeliveryController` — CRUD untuk manajemen pickup/delivery
- [ ] Endpoint untuk update status order: `PUT /orders/{id}/status`
- [ ] Endpoint riwayat order per customer: `GET /customers/{id}/orders`

### Low Priority (Fase 2)
- [ ] Dashboard/reporting: total order harian, omzet, dsb
- [ ] Laporan layanan terpopuler
- [ ] Ekspor laporan ke CSV/PDF

---

## Catatan Keamanan

1. **Jangan commit `.env`** ke Git — sudah ada di `.gitignore`
2. **Ganti password admin default** setelah setup (`lana@121212`)
3. **`APP_DEBUG=false`** saat production — jangan expose stack trace ke publik
4. **Selalu validasi input** sebelum simpan ke database
5. **Mass Assignment** sudah dilindungi dengan `$fillable` di semua model
6. **robots.txt** sudah block `/api/` dari crawler
