<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ReportController menyediakan endpoint laporan operasional laundry.
//
// Semua endpoint di sini bersifat READ-ONLY (hanya GET).
// Tidak ada create/update/delete karena ini adalah laporan, bukan manajemen data.
//
// Endpoint yang tersedia:
// GET /reports/summary        → ringkasan dashboard (gabungan semua metrik)
// GET /reports/orders/daily   → total order & omzet hari ini
// GET /reports/revenue        → omzet harian/mingguan/bulanan
// GET /reports/orders/pending → order yang belum selesai
// GET /reports/orders/unpaid  → order yang belum lunas
// GET /reports/services/top   → layanan paling sering dipakai
class ReportController extends Controller
{
    // summary() — GET /api/v1/reports/summary
    // Endpoint utama dashboard. Menggabungkan semua metrik penting dalam satu request.
    //
    // KENAPA disatukan dalam satu endpoint?
    // Supaya dashboard tidak perlu hit 5 endpoint berbeda saat pertama load.
    // Satu request = semua data yang dibutuhkan = lebih cepat.
    public function summary(): JsonResponse
    {
        // today() = Carbon object untuk tanggal hari ini (tanpa jam).
        // Berbeda dari now() yang menyertakan jam, menit, detik.
        $today = today();

        // ── 1. Metrik hari ini ──────────────────────────────────────────
        // whereDate('order_date', $today) = filter order yang order_date-nya = hari ini.
        // Berbeda dari where('order_date', $today) yang cek exact match termasuk jam.
        $todayOrders = Order::whereDate('order_date', $today);

        // count() = hitung jumlah baris yang match kondisi.
        $todayOrderCount   = (clone $todayOrders)->count();

        // sum('total_amount') = jumlahkan semua nilai kolom total_amount.
        // (float) = pastikan hasilnya decimal, bukan string.
        $todayRevenue      = (float) (clone $todayOrders)->sum('total_amount');

        // ── 2. Metrik bulan ini ─────────────────────────────────────────
        // whereMonth() = filter berdasarkan bulan (1-12).
        // whereYear()  = filter berdasarkan tahun (4 digit).
        // Keduanya dikombinasikan supaya tidak cross-year (misal Jan 2025 vs Jan 2026).
        $monthOrders = Order::whereMonth('order_date', now()->month)
                            ->whereYear('order_date', now()->year);

        $monthOrderCount   = (clone $monthOrders)->count();
        $monthRevenue      = (float) (clone $monthOrders)->sum('total_amount');

        // ── 3. Order belum selesai ──────────────────────────────────────
        // whereNotIn() = filter status yang TIDAK ada di array ini.
        // Order "belum selesai" = semua status kecuali completed dan cancelled.
        $pendingOrderCount = Order::whereNotIn('status', ['completed', 'cancelled'])->count();

        // ── 4. Order belum lunas ────────────────────────────────────────
        // whereIn() = filter status yang ADA di array ini.
        // 'unpaid'  = belum bayar sama sekali
        // 'partial' = sudah bayar sebagian, masih ada sisa
        $unpaidOrderCount  = Order::whereIn('payment_status', ['unpaid', 'partial'])->count();

        // ── 5. Layanan terpopuler (top 3) ──────────────────────────────
        $topServices = $this->getTopServices(3);

        return response()->json([
            'data' => [
                'today' => [
                    'order_count' => $todayOrderCount,
                    'revenue'     => $todayRevenue,
                ],
                'this_month' => [
                    'order_count' => $monthOrderCount,
                    'revenue'     => $monthRevenue,
                ],
                'operational' => [
                    // Order yang masih dalam proses (perlu perhatian operator)
                    'pending_orders' => $pendingOrderCount,
                    // Order yang masih ada tagihan (perlu ditagih kasir)
                    'unpaid_orders'  => $unpaidOrderCount,
                ],
                'top_services' => $topServices,
                // Tambahkan timestamp supaya client tahu data ini fresh dari kapan.
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // daily() — GET /api/v1/reports/orders/daily?date=2026-03-16
    // Total order dan omzet untuk satu hari tertentu.
    // Parameter 'date' opsional — kalau tidak dikirim, default ke hari ini.
    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // 'date' format: YYYY-MM-DD. Contoh: 2026-03-16
            // 'sometimes' = opsional, boleh tidak dikirim
            // 'date_format:Y-m-d' = validasi format tanggal spesifik
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        // Kalau date tidak dikirim, pakai hari ini.
        // Carbon::parse() = ubah string tanggal jadi Carbon object.
        $date = isset($validated['date'])
            ? \Carbon\Carbon::parse($validated['date'])
            : today();

        $orders = Order::whereDate('order_date', $date);

        // breakdown by status = berapa order per status di hari itu.
        // DB::raw('count(*) as count') = query SQL mentah untuk hitung.
        // select('status') + groupBy('status') = GROUP BY status di SQL.
        // get() = eksekusi query, hasilnya Collection of objects.
        $statusBreakdown = Order::whereDate('order_date', $date)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status'); // ubah jadi ['pending' => 2, 'washing' => 1, ...]

        return response()->json([
            'data' => [
                'date'             => $date->toDateString(),
                'order_count'      => (clone $orders)->count(),
                'revenue'          => (float) (clone $orders)->sum('total_amount'),
                'amount_collected' => (float) (clone $orders)->sum('amount_paid'),
                'amount_due'       => (float) (clone $orders)->sum('amount_due'),
                'status_breakdown' => $statusBreakdown,
            ],
        ]);
    }

    // revenue() — GET /api/v1/reports/revenue?period=weekly
    // Omzet berdasarkan periode: daily, weekly, atau monthly.
    // Parameter 'period' opsional — default ke 'monthly'.
    public function revenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // period harus salah satu dari 3 pilihan ini.
            'period' => ['sometimes', 'in:daily,weekly,monthly'],
        ]);

        $period = $validated['period'] ?? 'monthly';

        // Pilih rentang tanggal berdasarkan period yang diminta.
        // Carbon punya method startOf* dan endOf* yang sangat berguna.
        // startOfDay()   = jam 00:00:00
        // endOfDay()     = jam 23:59:59
        // startOfWeek()  = Senin jam 00:00:00
        // startOfMonth() = tanggal 1 jam 00:00:00
        $start = match ($period) {
            'daily'   => now()->startOfDay(),
            'weekly'  => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
        };

        $end = match ($period) {
            'daily'   => now()->endOfDay(),
            'weekly'  => now()->endOfWeek(),
            'monthly' => now()->endOfMonth(),
        };

        // whereBetween('kolom', [$start, $end]) = filter antara dua nilai.
        // Ini setara SQL: WHERE order_date BETWEEN $start AND $end
        $orders = Order::whereBetween('order_date', [$start, $end]);

        // Buat breakdown per hari dalam periode tersebut.
        // Format tanggal pakai DATE() function SQLite/MySQL untuk group by hari.
        $dailyBreakdown = Order::whereBetween('order_date', [$start, $end])
            ->select(
                DB::raw("date(order_date) as date"),
                DB::raw('count(*) as order_count'),
                DB::raw('sum(total_amount) as revenue'),
                DB::raw('sum(amount_paid) as collected')
            )
            ->groupBy(DB::raw("date(order_date)"))
            ->orderBy(DB::raw("date(order_date)"))
            ->get();

        return response()->json([
            'data' => [
                'period'    => $period,
                'date_from' => $start->toDateString(),
                'date_to'   => $end->toDateString(),
                'summary' => [
                    'order_count'      => (clone $orders)->count(),
                    'total_revenue'    => (float) (clone $orders)->sum('total_amount'),
                    'total_collected'  => (float) (clone $orders)->sum('amount_paid'),
                    'total_due'        => (float) (clone $orders)->sum('amount_due'),
                ],
                // Array berisi omzet per hari, berguna untuk buat grafik di frontend.
                'breakdown' => $dailyBreakdown,
            ],
        ]);
    }

    // pendingOrders() — GET /api/v1/reports/orders/pending
    // Semua order yang masih dalam proses (belum completed atau cancelled).
    // Berguna untuk operator melihat antrian kerja.
    public function pendingOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Filter by status tertentu, opsional.
            'status' => ['sometimes', 'in:pending,received,washing,drying,ironing,ready,delivered'],
            // Jumlah data per halaman (pagination).
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::whereNotIn('status', ['completed', 'cancelled'])
            // with('customer') = eager loading — ambil data customer sekaligus
            // dalam 1 query JOIN, bukan N+1 queries terpisah.
            // KENAPA penting? Kalau ada 50 order tanpa eager loading:
            //   1 query ambil orders + 50 queries ambil customer = 51 queries
            // Dengan eager loading:
            //   1 query orders + 1 query customers = 2 queries
            ->with('customer')
            ->orderBy('order_date');

        // Kalau ada filter status, tambahkan kondisi where.
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // paginate() = bagi hasil query jadi halaman-halaman.
        // Secara default Laravel tambah link navigasi halaman ke response.
        // per_page default 15 kalau tidak dikirim.
        $perPage = $validated['per_page'] ?? 15;
        $orders  = $query->paginate($perPage);

        return response()->json([
            'data' => $orders,
            'meta' => [
                'total_pending' => Order::whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
        ]);
    }

    // unpaidOrders() — GET /api/v1/reports/orders/unpaid
    // Semua order yang masih ada tagihan (unpaid atau partial).
    // Berguna untuk kasir melihat daftar piutang.
    public function unpaidOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::whereIn('payment_status', ['unpaid', 'partial'])
            ->with('customer')
            // Urutkan dari yang paling lama (amount_due terbesar dulu).
            ->orderByDesc('amount_due')
            ->paginate($validated['per_page'] ?? 15);

        // Hitung total piutang keseluruhan (semua order unpaid/partial).
        $totalDue = Order::whereIn('payment_status', ['unpaid', 'partial'])->sum('amount_due');

        return response()->json([
            'data' => $orders,
            'meta' => [
                // Ini angka yang ditampilkan di dashboard: "Total Piutang: Rp X"
                'total_amount_due' => (float) $totalDue,
            ],
        ]);
    }

    // topServices() — GET /api/v1/reports/services/top?limit=5
    // Layanan yang paling sering muncul di order_items.
    // Berguna untuk melihat layanan terlaris.
    public function topServices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // limit = berapa layanan teratas yang ingin ditampilkan. Default 10.
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $limit  = $validated['limit'] ?? 10;
        $result = $this->getTopServices($limit);

        return response()->json([
            'data' => $result,
        ]);
    }

    // ============================================================
    // PRIVATE HELPER METHOD
    // ============================================================
    // getTopServices() adalah method private — hanya bisa dipanggil
    // dari dalam class ini, tidak bisa diakses dari luar.
    // Dibuat private karena dipakai di dua tempat:
    // 1. summary() — untuk tampilkan top 3 di dashboard
    // 2. topServices() — untuk endpoint dedicated top services
    // Daripada tulis query yang sama dua kali, kita ekstrak ke method sendiri.
    // Ini prinsip DRY: Don't Repeat Yourself.
    private function getTopServices(int $limit): \Illuminate\Support\Collection
    {
        // Query ini melakukan:
        // 1. SELECT service_id, COUNT(*) as order_count, SUM(line_total) as total_revenue
        // 2. FROM order_items
        // 3. GROUP BY service_id
        // 4. ORDER BY order_count DESC
        // 5. LIMIT $limit
        //
        // with('service') = eager load data service (nama, harga, dll)
        // Hasilnya: [{service_id: 1, order_count: 25, total_revenue: 500000, service: {...}}]
        return OrderItem::select(
                'service_id',
                DB::raw('count(*) as order_count'),
                DB::raw('sum(line_total) as total_revenue'),
                DB::raw('sum(weight_kg) as total_weight_kg')
            )
            ->with('service:id,name,code,pricing_model,unit_price')
            // 'service:id,name,...' = eager load tapi hanya ambil kolom tertentu.
            // Ini lebih efisien dari with('service') yang ambil semua kolom.
            ->groupBy('service_id')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get();
    }
}
