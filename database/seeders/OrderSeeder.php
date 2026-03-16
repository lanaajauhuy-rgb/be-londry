<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('orders')->insert([
            [
                'order_number'        => 'ORD00001',
                'customer_id'         => 1,
                'received_by_user_id' => 1,
                'outlet_name'         => null,
                'order_date'          => now(),
                'estimated_done_at'   => now()->addDays(2),
                'completed_at'        => null,
                'status'              => 'washing',
                'payment_status'      => 'partial',
                // Kolom pricing yang lengkap sesuai migration terbaru.
                // total_amount = subtotal - discount + tax + extra_charge
                'subtotal'            => 142500,
                'discount_amount'     => 0,
                'tax_amount'          => 0,
                'extra_charge_amount' => 0,
                'total_amount'        => 142500,
                'amount_paid'         => 50000,
                'amount_due'          => 92500,
                'notes'               => 'Order awal dari seeder',
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'order_number'        => 'ORD00002',
                'customer_id'         => 2,
                'received_by_user_id' => 1,
                'outlet_name'         => null,
                'order_date'          => now()->subDays(1),
                'estimated_done_at'   => now()->addDays(1),
                'completed_at'        => null,
                'status'              => 'pending',
                'payment_status'      => 'unpaid',
                'subtotal'            => 75000,
                'discount_amount'     => 0,
                'tax_amount'          => 0,
                'extra_charge_amount' => 0,
                'total_amount'        => 75000,
                'amount_paid'         => 0,
                'amount_due'          => 75000,
                'notes'               => null,
                'created_at'          => now()->subDays(1),
                'updated_at'          => now()->subDays(1),
            ],
            [
                'order_number'        => 'ORD00003',
                'customer_id'         => 1,
                'received_by_user_id' => 1,
                'outlet_name'         => null,
                'order_date'          => now()->subDays(3),
                'estimated_done_at'   => now()->subDays(1),
                'completed_at'        => now()->subDays(1),
                'status'              => 'completed',
                'payment_status'      => 'paid',
                'subtotal'            => 50000,
                'discount_amount'     => 5000,
                'tax_amount'          => 0,
                'extra_charge_amount' => 0,
                'total_amount'        => 45000,
                'amount_paid'         => 45000,
                'amount_due'          => 0,
                'notes'               => 'Order sudah selesai',
                'created_at'          => now()->subDays(3),
                'updated_at'          => now()->subDays(1),
            ],
        ]);
    }
}
