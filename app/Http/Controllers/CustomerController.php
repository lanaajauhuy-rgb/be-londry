<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    // index() — GET /api/v1/customers
    // Mendukung: pencarian by nama/phone, pagination.
    //
    // Query params yang didukung:
    //   ?search=budi       → cari customer yang nama atau phone-nya mengandung "budi"
    //   ?per_page=20       → jumlah data per halaman (default 15)
    //   ?page=2            → halaman ke berapa (otomatis dari paginate())
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Customer::query()->withCount('orders')->latest();

        // Kalau ada parameter search, filter berdasarkan nama ATAU phone.
        // 'LIKE %keyword%' = cari yang mengandung keyword di posisi manapun.
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            // where + orWhere dibungkus closure supaya kondisi OR-nya terisolasi.
            // Tanpa closure: WHERE ... AND name LIKE ? OR phone LIKE ?  (salah)
            // Dengan closure: WHERE ... AND (name LIKE ? OR phone LIKE ?)  (benar)
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('customer_code', 'LIKE', "%{$search}%");
            });
        }

        $customers = $query->paginate($validated['per_page'] ?? 15);

        return response()->json($customers);
    }

    // store() — POST /api/v1/customers
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_code' => ['required', 'string', 'max:10', 'unique:customers,customer_code'],
            'name'          => ['required', 'string', 'max:50'],
            'phone'         => ['required', 'string', 'max:13'],
            'email'         => ['nullable', 'email', 'max:50'],
            'address'       => ['nullable', 'string', 'max:500'],
            'notes'         => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'message' => 'Customer berhasil dibuat',
            'data'    => $customer,
        ], 201);
    }

    // show() — GET /api/v1/customers/{id}
    // Menampilkan detail customer beserta statistik singkat.
    public function show(Customer $customer): JsonResponse
    {
        // load() = lazy eager loading — ambil relasi setelah model sudah ada.
        // Berbeda dari with() yang dilakukan saat query awal.
        // Di sini kita load orders terbaru untuk ditampilkan di profil customer.
        $customer->load([
            'orders' => function ($q) {
                // Hanya ambil 5 order terbaru dan field yang relevan saja.
                $q->latest()->limit(5)->select([
                    'id', 'order_number', 'order_date',
                    'status', 'payment_status', 'total_amount', 'customer_id',
                ]);
            },
        ]);

        return response()->json([
            'data' => $customer,
            'meta' => [
                // Statistik customer — berguna untuk tampilan profil.
                'total_orders'   => $customer->orders()->count(),
                'total_spent'    => (float) $customer->orders()->sum('total_amount'),
                'pending_orders' => $customer->orders()
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->count(),
            ],
        ]);
    }

    // update() — PUT /api/v1/customers/{id}
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'customer_code' => [
                'required', 'string', 'max:10',
                Rule::unique('customers', 'customer_code')->ignore($customer->id),
            ],
            'name'    => ['required', 'string', 'max:50'],
            'phone'   => ['required', 'string', 'max:13'],
            'email'   => ['nullable', 'email', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes'   => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer berhasil diupdate',
            'data'    => $customer->fresh(),
        ]);
    }

    // destroy() — DELETE /api/v1/customers/{id}
    public function destroy(Customer $customer): JsonResponse
    {
        // Cek dulu apakah customer masih punya order yang aktif.
        // Tidak aman menghapus customer yang masih ada order berjalan.
        $activeOrders = $customer->orders()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        if ($activeOrders > 0) {
            return response()->json([
                'message' => 'Customer tidak bisa dihapus karena masih memiliki '
                             . $activeOrders . ' order aktif.',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus',
        ]);
    }

    // orders() — GET /api/v1/customers/{id}/orders
    // Riwayat semua order milik customer ini, dengan pagination.
    public function orders(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'status'         => ['sometimes', 'string'],
            'payment_status' => ['sometimes', 'in:unpaid,partial,paid,refunded'],
            'per_page'       => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $customer->orders()->latest('order_date');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        $orders = $query->paginate($validated['per_page'] ?? 15);

        return response()->json($orders);
    }
}
