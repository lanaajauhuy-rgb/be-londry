<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

// ShouldQueue = jalankan listener secara async di background queue.
// Dengan attribute #[EventListener] listener ini TIDAK akan di-auto-discover —
// sudah didaftarkan manual di AppServiceProvider via Event::listen().
class SendOrderStatusNotification implements ShouldQueue
{
    public function handle(OrderStatusChanged $event): void
    {
        $order     = $event->order;
        $newStatus = $event->newStatus;

        $notifyStatuses = ['received', 'ready', 'completed', 'cancelled'];
        if (! in_array($newStatus, $notifyStatuses)) {
            return;
        }

        $customer = $order->customer ?? $order->load('customer')->customer;
        if (! $customer) {
            return;
        }

        $messages = [
            'received'  => "Halo {$customer->name}, laundry kamu nomor {$order->order_number} sudah kami terima dan sedang diproses. Terima kasih!",
            'ready'     => "Halo {$customer->name}, laundry kamu nomor {$order->order_number} sudah SELESAI dan siap diambil! Sisa tagihan: Rp " . number_format((float) $order->amount_due, 0, ',', '.'),
            'completed' => "Halo {$customer->name}, terima kasih sudah menggunakan Laundry Lastri! Order {$order->order_number} sudah selesai. Sampai jumpa lagi!",
            'cancelled' => "Halo {$customer->name}, order kamu nomor {$order->order_number} telah dibatalkan. Hubungi kami jika ada pertanyaan.",
        ];

        $message = $messages[$newStatus] ?? null;
        if (! $message) {
            return;
        }

        Log::info('[Notifikasi Order]', [
            'order_number' => $order->order_number,
            'customer'     => $customer->name,
            'phone'        => $customer->phone,
            'status'       => $newStatus,
            'message'      => $message,
        ]);

        $this->sendWhatsApp($customer->phone, $message);
    }

    private function sendWhatsApp(string $phone, string $message): void
    {
        $token = config('services.fonnte.token');
        if (! $token) {
            Log::warning('FONNTE_TOKEN belum diset di .env');
            return;
        }

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
                CURLOPT_TIMEOUT    => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                Log::warning('Fonnte WA gagal', [
                    'phone'    => $phone,
                    'response' => $response,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp notification error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
