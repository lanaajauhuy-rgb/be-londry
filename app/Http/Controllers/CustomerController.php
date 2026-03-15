<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // dipakai untuk validasi yang butuh logika lebih kompleks

// CustomerController menangani semua operasi CRUD untuk data customer.
// CRUD = Create, Read, Update, Delete — 4 operasi dasar pengelolaan data.
// Diakses lewat Route::apiResource('customers', ...) yang mendaftarkan 5 route otomatis.
class CustomerController extends Controller
{
    // index() — dipanggil saat GET /api/v1/customers
    // Tugasnya: ambil semua data customer, kembalikan sebagai JSON.
    public function index(): JsonResponse
    {
        return response()->json([
            // Customer::latest() = ambil semua data customer, diurutkan dari yang terbaru.
            // latest() secara default mengurutkan berdasarkan kolom 'created_at' DESC.
            // ->get() = eksekusi query dan ambil hasilnya sebagai Collection (semacam array).
            //
            // Cara alternatif yang sama:
            // Customer::orderBy('created_at', 'desc')->get()
            'data' => Customer::latest()->get(),
        ]);
    }

    // store() — dipanggil saat POST /api/v1/customers
    // Tugasnya: validasi data, simpan customer baru ke database.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_code' => ['required', 'string', 'max:10', 'unique:customers,customer_code'],
            'name'          => ['required', 'string', 'max:50'],
            'phone'         => ['required', 'string', 'max:13'],
            'email'         => ['nullable', 'email', 'max:50'],
            'address'       => ['required', 'string', 'max:500'],
            'notes'         => ['nullable', 'string', 'max:255'],
        ]);

        // Karena semua field di $validated sama persis dengan $fillable di Model Customer,
        // kita bisa langsung pass $validated tanpa tulis field satu per satu.
        // Cara alternatif (lebih verbose tapi lebih eksplisit):
        // Customer::create(['name' => $validated['name'], 'phone' => $validated['phone'], ...])
        $customer = Customer::create($validated);

        return response()->json([
            'message' => 'Customer berhasil dibuat',
            'data'    => $customer,
        ], 201);
    }

    // show() — dipanggil saat GET /api/v1/customers/{id}
    // Tugasnya: kembalikan detail satu customer berdasarkan ID.
    //
    // Parameter $customer sudah berupa object Customer, BUKAN integer ID.
    // Ini namanya "Route Model Binding" — Laravel otomatis cari Customer berdasarkan
    // ID di URL dan inject object-nya ke parameter.
    // Kalau Customer dengan ID itu tidak ada, Laravel otomatis balas 404.
    // Kamu tidak perlu tulis Customer::findOrFail($id) secara manual.
    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'data' => $customer,
        ]);
    }

    // update() — dipanggil saat PUT /api/v1/customers/{id}
    // Tugasnya: validasi data baru, perbarui data customer yang sudah ada.
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'customer_code' => [
                'required',
                'string',
                'max:10',
                // Rule::unique()->ignore() dibutuhkan karena:
                // Saat update, kita tidak mau validasi unique menolak nilai yang
                // sudah dimiliki customer ini sendiri.
                // Contoh: customer id=5 punya customer_code='CUS005'.
                // Kalau update tanpa ->ignore(), validasi unique akan error karena
                // 'CUS005' sudah ada di database (milik customer itu sendiri).
                // ->ignore($customer->id) = "abaikan baris dengan id ini saat cek unique".
                Rule::unique('customers', 'customer_code')->ignore($customer->id),
            ],
            'name'    => ['required', 'string', 'max:50'],
            'phone'   => ['required', 'string', 'max:13'],
            'email'   => ['nullable', 'email', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'notes'   => ['nullable', 'string', 'max:255'],
        ]);

        // $customer->update() — update data customer yang sudah diambil lewat Route Model Binding.
        // Berbeda dengan Customer::update() yang butuh kondisi where.
        // Di sini $customer sudah spesifik ke satu baris, jadi langsung update.
        $customer->update($validated);

        return response()->json([
            'message' => 'Customer berhasil diupdate',
            'data'    => $customer,
        ]);
    }

    // destroy() — dipanggil saat DELETE /api/v1/customers/{id}
    // Tugasnya: hapus customer dari database.
    public function destroy(Customer $customer): JsonResponse
    {
        // $customer->delete() hapus baris ini dari database.
        // Karena tabel customers tidak pakai softDelete, data benar-benar dihapus permanen.
        // Kalau mau "hapus lunak" (data tetap ada tapi ditandai deleted), perlu tambah
        // SoftDeletes trait di Model dan kolom deleted_at di migration.
        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus',
        ]);
        // Tidak return data customer — karena sudah dihapus, tidak ada yang perlu dikembalikan.
    }
}
