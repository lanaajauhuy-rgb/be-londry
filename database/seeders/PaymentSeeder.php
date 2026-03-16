<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        // Payment untuk ORD00001 (status partial — sudah bayar sebagian)
        DB::table('payments')->insert([
            [
                'order_id'        => 1,
                'payment_number'  => 'PAY260316001',
                'payment_date'    => now()->subHours(2),
                'method'          => 'cash',
                'amount'          => 50000.00,
                'paid_by_user_id' => 1,
                'reference_no'    => null,
                'notes'           => 'DP pertama',
                'created_at'      => now()->subHours(2),
                'updated_at'      => now()->subHours(2),
            ],
            // Payment untuk ORD00003 (status paid — sudah lunas)
            [
                'order_id'        => 3,
                'payment_number'  => 'PAY260316002',
                'payment_date'    => now()->subDays(1),
                'method'          => 'qris',
                'amount'          => 45000.00,
                'paid_by_user_id' => 1,
                'reference_no'    => 'QRIS-20260315-001',
                'notes'           => 'Lunas saat ambil',
                'created_at'      => now()->subDays(1),
                'updated_at'      => now()->subDays(1),
            ],
        ]);
    }
}
