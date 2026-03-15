<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('orders')->insert([
            'order_number' => 'ORD00001',
            'customer_id' => 1,
            'received_by_user_id' => 1,
            'outlet_name' => null,
            'order_date' => now(),
            'estimated_done_at' => now()->addDays(2),
            'completed_at' => null,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 142500,
            'total_amount' => 142500,
            'amount_paid' => 0,
            'amount_due' => 142500,
            'notes' => 'Order awal dari seeder',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
