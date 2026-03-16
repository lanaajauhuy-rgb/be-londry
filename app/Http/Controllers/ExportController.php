<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

// ExportController menyediakan endpoint untuk export data ke berbagai format.
//
// Format yang didukung:
//   CSV  = bisa dibuka di Excel/Google Sheets, ringan, tidak butuh library
//   HTML = bisa di-print sebagai PDF dari browser (Ctrl+P → Save as PDF)
//
// Semua export butuh login dan role admin.
class ExportController extends Controller
{
    // ordersCSV() — GET /api/v1/export/orders/csv
    // Export data order ke format CSV.
    //
    // Query params:
    //   ?date_from=2026-03-01&date_to=2026-03-31  → filter rentang tanggal
    //   ?status=completed                          → filter by status
    //   ?payment_status=paid                       → filter by payment status
    public function ordersCSV(Request $request): Response
    {
        $validated = $request->validate([
            'date_from'      => ['sometimes', 'date_format:Y-m-d'],
            'date_to'        => ['sometimes', 'date_format:Y-m-d'],
            'status'         => ['sometimes', 'string'],
            'payment_status' => ['sometimes', 'in:unpaid,partial,paid,refunded'],
        ]);

        $query = Order::with('customer:id,name,phone')
            ->orderBy('order_date');

        if (! empty($validated['date_from'])) {
            $query->whereDate('order_date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('order_date', '<=', $validated['date_to']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        $orders = $query->get();

        // Buat konten CSV secara manual.
        // CSV = Comma Separated Values — format teks sederhana yang dibaca Excel.
        // Setiap baris = satu order. Kolom dipisahkan koma.
        $csvLines = [];

        // Header baris pertama — nama kolom
        $csvLines[] = implode(',', [
            'No Order', 'Tanggal', 'Customer', 'Phone',
            'Status', 'Status Bayar', 'Subtotal', 'Diskon',
            'Pajak', 'Extra', 'Total', 'Sudah Dibayar', 'Sisa',
        ]);

        foreach ($orders as $order) {
            // Bungkus nilai dengan tanda kutip supaya koma di dalam nilai
            // tidak dianggap sebagai pemisah kolom CSV.
            $csvLines[] = implode(',', [
                '"' . $order->order_number . '"',
                '"' . $order->order_date . '"',
                '"' . ($order->customer?->name ?? '-') . '"',
                '"' . ($order->customer?->phone ?? '-') . '"',
                '"' . $order->status . '"',
                '"' . $order->payment_status . '"',
                $order->subtotal,
                $order->discount_amount,
                $order->tax_amount,
                $order->extra_charge_amount,
                $order->total_amount,
                $order->amount_paid,
                $order->amount_due,
            ]);
        }

        $csvContent = implode("\n", $csvLines);

        // Nama file dinamis berdasarkan tanggal export
        $filename = 'orders-' . now()->format('Y-m-d') . '.csv';

        // Return response dengan header yang memberitahu browser ini adalah file download.
        // 'Content-Type: text/csv' = tipe file CSV
        // 'Content-Disposition: attachment' = browser download, bukan tampilkan
        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
        ]);
    }

    // paymentsCSV() — GET /api/v1/export/payments/csv
    // Export data pembayaran ke CSV untuk rekonsiliasi keuangan.
    public function paymentsCSV(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to'   => ['sometimes', 'date_format:Y-m-d'],
            'method'    => ['sometimes', 'in:cash,transfer,qris,ewallet'],
        ]);

        $query = Payment::with([
            'order:id,order_number,customer_id',
            'order.customer:id,name,phone',
            'paidBy:id,name',
        ])->orderBy('payment_date');

        if (! empty($validated['date_from'])) {
            $query->whereDate('payment_date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('payment_date', '<=', $validated['date_to']);
        }
        if (! empty($validated['method'])) {
            $query->where('method', $validated['method']);
        }

        $payments = $query->get();

        $csvLines = [];
        $csvLines[] = implode(',', [
            'No Bayar', 'Tanggal', 'No Order', 'Customer',
            'Metode', 'Jumlah', 'Referensi', 'Kasir', 'Catatan',
        ]);

        foreach ($payments as $payment) {
            $csvLines[] = implode(',', [
                '"' . $payment->payment_number . '"',
                '"' . $payment->payment_date . '"',
                '"' . ($payment->order?->order_number ?? '-') . '"',
                '"' . ($payment->order?->customer?->name ?? '-') . '"',
                '"' . $payment->method . '"',
                $payment->amount,
                '"' . ($payment->reference_no ?? '') . '"',
                '"' . ($payment->paidBy?->name ?? '-') . '"',
                '"' . str_replace('"', '""', $payment->notes ?? '') . '"',
            ]);
        }

        $csvContent = implode("\n", $csvLines);
        $filename   = 'payments-' . now()->format('Y-m-d') . '.csv';

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
        ]);
    }

    // revenueHTML() — GET /api/v1/export/revenue/print
    // Export laporan omzet dalam format HTML yang bisa di-print sebagai PDF.
    // Buka URL ini di browser → Ctrl+P → Save as PDF.
    public function revenueHTML(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d'],
        ]);

        $dateFrom = \Carbon\Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo   = \Carbon\Carbon::parse($validated['date_to'])->endOfDay();

        // Ambil data summary
        $orders = Order::whereBetween('order_date', [$dateFrom, $dateTo])->get();

        $summary = [
            'total_orders'    => $orders->count(),
            'total_revenue'   => $orders->sum('total_amount'),
            'total_collected' => $orders->sum('amount_paid'),
            'total_due'       => $orders->sum('amount_due'),
            'paid_orders'     => $orders->where('payment_status', 'paid')->count(),
            'unpaid_orders'   => $orders->whereIn('payment_status', ['unpaid', 'partial'])->count(),
        ];

        // Breakdown per hari
        $dailyData = Order::whereBetween('order_date', [$dateFrom, $dateTo])
            ->select(
                DB::raw("date(order_date) as date"),
                DB::raw('count(*) as order_count'),
                DB::raw('sum(total_amount) as revenue'),
                DB::raw('sum(amount_paid) as collected')
            )
            ->groupBy(DB::raw("date(order_date)"))
            ->orderBy(DB::raw("date(order_date)"))
            ->get();

        // Generate HTML — format sederhana yang bersih saat di-print
        $html = $this->buildRevenueHTML($summary, $dailyData, $validated['date_from'], $validated['date_to']);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    // buildRevenueHTML() — helper private untuk generate HTML laporan
    private function buildRevenueHTML(array $summary, $dailyData, string $dateFrom, string $dateTo): string
    {
        $formatRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

        $rows = '';
        foreach ($dailyData as $day) {
            $rows .= "<tr>
                <td>{$day->date}</td>
                <td style='text-align:center'>{$day->order_count}</td>
                <td style='text-align:right'>{$formatRp($day->revenue)}</td>
                <td style='text-align:right'>{$formatRp($day->collected)}</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Omzet Laundry Lastri</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; padding: 24px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #666; margin-bottom: 20px; font-size: 12px; }
        .summary { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 12px 16px; min-width: 140px; }
        .card-label { font-size: 11px; color: #888; margin-bottom: 4px; }
        .card-value { font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f5f5f5; padding: 8px 10px; text-align: left; border: 1px solid #ddd; font-size: 12px; }
        td { padding: 7px 10px; border: 1px solid #eee; font-size: 12px; }
        tr:nth-child(even) { background: #fafafa; }
        .total-row { font-weight: bold; background: #f0f7ff !important; }
        .print-note { margin-top: 20px; font-size: 11px; color: #999; }
        @media print { .print-note { display: none; } }
    </style>
</head>
<body>
    <h1>Laporan Omzet — Laundry Lastri</h1>
    <p class="subtitle">Periode: {$dateFrom} s/d {$dateTo} &nbsp;|&nbsp; Dicetak: {$this->nowFormatted()}</p>

    <div class="summary">
        <div class="card">
            <div class="card-label">Total Order</div>
            <div class="card-value">{$summary['total_orders']}</div>
        </div>
        <div class="card">
            <div class="card-label">Total Omzet</div>
            <div class="card-value">{$formatRp($summary['total_revenue'])}</div>
        </div>
        <div class="card">
            <div class="card-label">Terkumpul</div>
            <div class="card-value">{$formatRp($summary['total_collected'])}</div>
        </div>
        <div class="card">
            <div class="card-label">Sisa Piutang</div>
            <div class="card-value">{$formatRp($summary['total_due'])}</div>
        </div>
        <div class="card">
            <div class="card-label">Lunas / Belum</div>
            <div class="card-value">{$summary['paid_orders']} / {$summary['unpaid_orders']}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th style="text-align:center">Jml Order</th>
                <th style="text-align:right">Omzet</th>
                <th style="text-align:right">Terkumpul</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
            <tr class="total-row">
                <td>TOTAL</td>
                <td style="text-align:center">{$summary['total_orders']}</td>
                <td style="text-align:right">{$formatRp($summary['total_revenue'])}</td>
                <td style="text-align:right">{$formatRp($summary['total_collected'])}</td>
            </tr>
        </tbody>
    </table>

    <p class="print-note">Untuk simpan sebagai PDF: Ctrl+P → Pilih "Save as PDF" → Save</p>
</body>
</html>
HTML;
    }

    private function nowFormatted(): string
    {
        return now()->format('d/m/Y H:i');
    }
}
