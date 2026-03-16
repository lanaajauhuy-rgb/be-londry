<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

// SendOrderStatusNotification — Listener yang bereaksi saat status order berubah.
//
// implements ShouldQueue = Listener ini dijalankan secara ASYNC (background queue).
// Artinya: Controller tidak perlu tunggu notifikasi terkirim sebelum return response.
// API tetap cepat walau notifikasi butuh waktu (WA/email bisa lambat).
//
// CARA AKTIFKAN QUEUE:
// 1. Set QUEUE_CONNECTION=database di .env
// 2. Jalankan: php artisan queue:work
// 3. Atau untuk dev: php artisan queue:listen
class SendOrderStatusNotification implements ShouldQueue
{
    // handle() dipanggil otomatis oleh Laravel saat event OrderStatusChanged di-dispatch.
    // Parameter $event berisi data yang dikirim dari Controller.
    public function handle(OrderStatusChanged $event): void
    {
        $order     = $event->order;
        $newStatus = $event->newStatus;

        // Hanya kirim notifikasi untuk status yang penting buat customer.
        // Status internal seperti 'washing', 'drying' tidak perlu dinotifikasi.
        $notifyStatuses = ['received', 'ready', 'completed', 'cancelled'];
        if (! in_array($newStatus, $notifyStatuses)) {
            return;
        }

        // Load relasi customer kalau belum di-load.
        // ?-> = nullsafe operator: kalau $order->customer null, tidak error.
        $customer = $order->customer ?? $order->load('customer')->customer;
        if (! $customer) {
            return;
        }

        // Pesan notifikasi per status — disesuaikan dengan kebutuhan laundry.
        $messages = [
            'received'  => "Halo {$customer->name}, laundry kamu nomor {$order->order_number} "
                         . "sudah kami terima dan sedang diproses. Terima kasih!",

            'ready'     => "Halo {$customer->name}, laundry kamu nomor {$order->order_number} "
                         . "sudah SELESAI dan siap diambil! "
                         . "Sisa tagihan: Rp " . number_format((float) $order->amount_due, 0, ',', '.'),

            'completed' => "Halo {$customer->name}, terima kasih sudah menggunakan Laundry Lastri! "
                         . "Order {$order->order_number} sudah selesai. Sampai jumpa lagi!",

            'cancelled' => "Halo {$customer->name}, order kamu nomor {$order->order_number} "
                         . "telah dibatalkan. Hubungi kami jika ada pertanyaan.",
        ];

        $message = $messages[$newStatus] ?? null;
        if (! $message) {
            return;
        }

        // ============================================================
        // CHANNEL NOTIFIKASI — pilih yang ingin diaktifkan
        // ============================================================

        // CHANNEL 1: Log — selalu aktif, berguna untuk debugging.
        // Lihat hasilnya di storage/logs/laravel.log
        \Illuminate\Support\Facades\Log::info('[Notifikasi Order]', [
            'order_number' => $order->order_number,
            'customer'     => $customer->name,
            'phone'        => $customer->phone,
            'status'       => $newStatus,
            'message'      => $message,
        ]);

        // CHANNEL 2: WhatsApp via Fonnte.
        // Cara aktifkan:
        // 1. Daftar di https://fonnte.com
        // 2. Hubungkan nomor WA laundry kamu
        // 3. Copy API token dari dashboard Fonnte
        // 4. Tambah FONNTE_TOKEN=xxx ke file .env
        // 5. Uncomment baris di bawah ini
         $this->sendWhatsApp($customer->phone, $message);

        // CHANNEL 3: Email.
        // Cara aktifkan:
        // 1. Set MAIL_* di .env (SMTP, Mailgun, dll)
        // 2. Jalankan: php artisan make:mail OrderStatusMail
        // 3. Uncomment baris di bawah ini
        // if ($customer->email) {
        //     \Illuminate\Support\Facades\Mail::to($customer->email)
        //         ->send(new \App\Mail\OrderStatusMail($order, $message));
        // }
    }

    // sendWhatsApp() — kirim pesan WA via Fonnte API.
    // Fonnte adalah gateway WA Indonesia yang populer, harga per pesan ~Rp 50-100.
    private function sendWhatsApp(string $phone, string $message): void
    {
        $token = config('services.fonnte.token');
        if (! $token) {
            \Illuminate\Support\Facades\Log::warning('FONNTE_TOKEN belum diset di .env');
            return;
        }

        // Normalisasi nomor HP ke format internasional.
        // 08xxxxxxxx → 628xxxxxxxx
        $phone = ltrim($phone, '+');
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        try {
            $ch = curl_init('https://api.fonnte.com/send');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'target'  => $phone,
                    'message' => $message,
                ],
                CURLOPT_HTTPHEADER => ["Authorization: {$token}"],
                CURLOPT_TIMEOUT    => 10, // timeout 10 detik
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                \Illuminate\Support\Facades\Log::warning('Fonnte WA gagal', [
                    'phone'    => $phone,
                    'response' => $response,
                ]);
            }
        } catch (\Throwable $e) {
            // Log error tapi JANGAN crash — notifikasi gagal tidak boleh
            // menghentikan proses utama. Order tetap berjalan normal.
            \Illuminate\Support\Facades\Log::error('WhatsApp notification error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
