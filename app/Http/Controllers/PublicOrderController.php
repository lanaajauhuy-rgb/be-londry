<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// PublicOrderController menangani pembuatan order dari pelanggan umum.
// Endpoint ini TIDAK butuh login — siapa saja bisa akses.
//
// Alur kerjanya:
// 1. Validasi data dari request
// 2. Cari atau buat Customer berdasarkan nomor HP
// 3. Hitung subtotal dari semua items
// 4. Buat Order
// 5. Buat OrderItem untuk setiap item
// 6. Buat Delivery untuk jadwal pickup
// Semua langkah 2-6 dibungkus dalam DB::transaction supaya
// kalau satu langkah gagal, semua langkah sebelumnya dibatalkan.
class PublicOrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Pakai Validator::make() manual, bukan $request->validate().
        // KENAPA? Karena $request->validate() akan redirect ke halaman sebelumnya
        // kalau validasi gagal di web route. Di API route, ini menghasilkan
        // response HTML, bukan JSON. Dengan Validator manual, kita bisa
        // kontrol sendiri response-nya supaya selalu JSON.
        //
        // ALTERNATIF: bisa pakai $request->validate() kalau sudah yakin
        // client selalu kirim header 'Accept: application/json'.
        // Laravel akan return JSON kalau header itu ada.
        $validator = Validator::make($request->all(), [
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:20'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            // address customer wajib diisi karena kolom customers.address NOT NULL di DB.
            // Kalau nullable, DB akan error saat customer baru dibuat tanpa address.
            'customer.address' => ['required', 'string'],
            'customer.notes' => ['nullable', 'string'],

            'pickup.address' => ['required', 'string'],
            'pickup.scheduled_at' => ['nullable', 'date'],
            'pickup.notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'exists:services,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // DB::transaction() membungkus beberapa operasi DB menjadi satu unit atomik.
        //
        // ATOMIK artinya: semua berhasil, atau semua dibatalkan.
        // Analogi: transfer uang bank — debit A dan kredit B harus sama-sama berhasil.
        // Kalau kredit B gagal, debit A harus dibatalkan juga.
        //
        // KENAPA perlu transaction di sini?
        // Karena kita membuat 3 record berbeda: Customer, Order, OrderItem, Delivery.
        // Kalau Order berhasil tapi OrderItem gagal — data jadi setengah-setengah (corrupt).
        // Dengan transaction, kalau OrderItem gagal → Order juga otomatis dihapus kembali.
        //
        // Closure di sini menerima 'use ($validated)' supaya bisa akses
        // variable $validated dari luar scope closure.
        $result = DB::transaction(function () use ($validated) {
            // Pecah array $validated menjadi bagian-bagian kecil supaya lebih mudah dibaca.
            // Ini hanya memindahkan referensi, tidak copy data — efisien.
            $customerData = $validated['customer'];
            $pickupData   = $validated['pickup'];
            $itemsData    = $validated['items'];

            // Cari customer berdasarkan nomor HP.
            // ->first() = ambil 1 baris pertama yang cocok, atau null kalau tidak ada.
            // ALTERNATIF: ->firstOrCreate([...]) — tapi kita butuh logika update,
            // jadi lebih baik cek manual seperti ini.
            $customer = Customer::where('phone', $customerData['phone'])->first();

            if ($customer) {
                // Customer sudah pernah order sebelumnya — update datanya.
                // '?? $customer->email' = kalau client tidak kirim email baru,
                // pertahankan email lama yang sudah ada.
                $customer->update([
                    'name'    => $customerData['name'],
                    'email'   => $customerData['email']   ?? $customer->email,
                    'address' => $customerData['address'] ?? $customer->address,
                    'notes'   => $customerData['notes']   ?? $customer->notes,
                ]);
            } else {
                // Customer baru — buat record baru dengan kode unik.
                // generateCustomerCode() dipanggil lewat $this karena dia
                // adalah private method di class ini.
                $customer = Customer::create([
                    'customer_code' => $this->generateCustomerCode(),
                    'name'          => $customerData['name'],
                    'phone'         => $customerData['phone'],
                    'email'         => $customerData['email']   ?? null,
                    'address'       => $customerData['address'],
                    'notes'         => $customerData['notes']   ?? null,
                ]);
            }

            // $subtotal akan diakumulasi dari setiap item dalam loop.
            // $preparedItems menyimpan data items yang sudah dihitung, siap di-insert.
            // KENAPA tidak langsung insert di dalam loop?
            // Supaya kalau ada item yang gagal validasi weight_kg, kita throw exception
            // SEBELUM ada data yang tersimpan ke DB. Lebih aman.
            $subtotal      = 0;
            $preparedItems = [];

            foreach ($itemsData as $index => $item) {
                // findOrFail() = cari Service by id, kalau tidak ada → throw 404.
                // Di sini aman karena service_id sudah divalidasi 'exists:services,id'
                // sebelumnya. findOrFail sebagai safety net kedua.
                $service = Service::findOrFail($item['service_id']);

                // Cast ke tipe yang tepat supaya kalkulasi akurat.
                // (int)  = paksa jadi integer. '3.7' jadi 3.
                // (float) = paksa jadi decimal. '3' jadi 3.0.
                $qty      = (int)   $item['qty'];
                $unitPrice = (float) $service->unit_price;

                // isset() = cek apakah key ada DAN nilainya bukan null.
                // Berbeda dari array_key_exists() yang hanya cek key-nya ada atau tidak.
                $weightKg = isset($item['weight_kg']) ? (float) $item['weight_kg'] : null;

                if ($service->pricing_model === 'per_kg') {
                    // Layanan kiloan: harga dihitung dari berat.
                    // Kalau weight_kg tidak dikirim → throw ValidationException.
                    // ValidationException otomatis di-catch oleh Laravel dan
                    // dikembalikan sebagai JSON 422 ke client.
                    if ($weightKg === null || $weightKg <= 0) {
                        $field = 'items.' . $index . '.weight_kg';
                        throw ValidationException::withMessages([
                            $field => ['weight_kg wajib diisi dan lebih dari 0 untuk layanan per_kg.'],
                        ]);
                    }
                    $lineTotal = $weightKg * $unitPrice;

                } elseif ($service->pricing_model === 'per_item') {
                    // Layanan per satuan: harga dihitung dari jumlah.
                    $lineTotal = $qty * $unitPrice;

                } else {
                    // Layanan flat: harga tetap tidak peduli berat/jumlah.
                    $lineTotal = $unitPrice;
                }

                // += adalah shorthand untuk: $subtotal = $subtotal + $lineTotal
                $subtotal += $lineTotal;

                // MAPPING pricing_model → service_type.
                // Tabel order_items pakai ENUM('kiloan', 'per_item').
                // Tabel services pakai pricing_model ('per_kg', 'per_item', 'flat').
                // Keduanya berbeda — harus di-mapping supaya tidak error DB.
                // '?? per_item' = fallback kalau pricing_model tidak ada di map.
                $serviceTypeMap = [
                    'per_kg'   => 'kiloan',
                    'per_item' => 'per_item',
                    'flat'     => 'per_item',
                ];
                $serviceType = $serviceTypeMap[$service->pricing_model] ?? 'per_item';

                // Simpan data item yang sudah diolah ke array sementara.
                // [] di akhir array = push elemen baru ke array (seperti append).
                $preparedItems[] = [
                    'service_id'   => $service->id,
                    'service_type' => $serviceType,
                    'item_name'    => $service->name,
                    'qty'          => $qty,
                    'weight_kg'    => $weightKg,
                    'unit_price'   => $unitPrice,
                    'line_total'   => $lineTotal,
                    'notes'        => $item['notes'] ?? null,
                ];
            }

            // Semua item sudah dihitung tanpa error — baru buat Order-nya.
            $order = Order::create([
                'order_number'        => $this->generateOrderNumber(),
                'customer_id'         => $customer->id,
                'received_by_user_id' => null, // belum ada admin yang handle
                'outlet_name'         => null,
                'order_date'          => now(), // now() = waktu sekarang (Carbon)
                'estimated_done_at'   => null,
                'completed_at'        => null,
                'status'              => 'pending',
                'payment_status'      => 'unpaid',
                'subtotal'            => $subtotal,
                'total_amount'        => $subtotal, // belum ada diskon/ongkir
                'amount_paid'         => 0,
                'amount_due'          => $subtotal,
                'notes'               => $pickupData['notes'] ?? null,
            ]);

            // Insert semua order_items sekaligus setelah order berhasil dibuat.
            foreach ($preparedItems as $preparedItem) {
                OrderItem::create([
                    'order_id'     => $order->id, // $order->id = id yang baru di-generate DB
                    'service_id'   => $preparedItem['service_id'],
                    'service_type' => $preparedItem['service_type'],
                    'item_name'    => $preparedItem['item_name'],
                    'qty'          => $preparedItem['qty'],
                    'weight_kg'    => $preparedItem['weight_kg'],
                    'unit_price'   => $preparedItem['unit_price'],
                    'line_total'   => $preparedItem['line_total'],
                    'notes'        => $preparedItem['notes'],
                ]);
            }

            // Buat record Delivery untuk jadwal pickup.
            Delivery::create([
                'order_id'        => $order->id,
                'type'            => 'pickup', // pickup = jemput ke alamat customer
                'address'         => $pickupData['address'],
                'scheduled_at'    => $pickupData['scheduled_at'] ?? null,
                'completed_at'    => null, // belum selesai
                'courier_user_id' => null, // belum di-assign kurir
                'status'          => 'pending',
                'notes'           => $pickupData['notes'] ?? null,
            ]);

            // Return array biasa dari closure — BUKAN JsonResponse.
            // KENAPA? Karena transaction closure seharusnya hanya berurusan
            // dengan data, bukan HTTP response. Pembuatan response dilakukan
            // di luar closure supaya separation of concerns terjaga.
            return [
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
                'customer_id'    => $customer->id,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount'   => $order->total_amount,
            ];
        });

        return response()->json([
            'message' => 'Order berhasil dibuat',
            'data'    => $result,
        ], 201);
    }

    private function generateCustomerCode(): string
    {
        $lastCustomer = Customer::latest('id')->first();
        $nextNumber = $lastCustomer ? $lastCustomer->id + 1 : 1;

        return 'CUS'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }

    private function generateOrderNumber(): string
    {
        // Format: ORD + tahun 2 digit + bulan + tanggal + jam + menit + detik
        // Contoh hasil: ORD260314153045 (14 karakter, aman untuk VARCHAR(30))
        return 'ORD'.now()->format('ymdHis');
    }
}
