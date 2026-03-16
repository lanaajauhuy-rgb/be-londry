<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusHistorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // History untuk ORD00001 (sedang washing)
        DB::table('order_status_histories')->insert([
            [
                'order_id'           => 1,
                'status'             => 'pending',
                'changed_by_user_id' => 1,
                'changed_at'         => $now->copy()->subHours(5),
                'notes'              => 'Order masuk',
                'created_at'         => $now->copy()->subHours(5),
                'updated_at'         => $now->copy()->subHours(5),
            ],
            [
                'order_id'           => 1,
                'status'             => 'received',
                'changed_by_user_id' => 1,
                'changed_at'         => $now->copy()->subHours(4),
                'notes'              => 'Barang diterima dan ditimbang',
                'created_at'         => $now->copy()->subHours(4),
                'updated_at'         => $now->copy()->subHours(4),
            ],
            [
                'order_id'           => 1,
                'status'             => 'washing',
                'changed_by_user_id' => 1,
                'changed_at'         => $now->copy()->subHours(2),
                'notes'              => 'Masuk mesin cuci',
                'created_at'         => $now->copy()->subHours(2),
                'updated_at'         => $now->copy()->subHours(2),
            ],
            // History untuk ORD00003 (completed)
            [
                'order_id'           => 3,
                'status'             => 'pending',
                'changed_by_user_id' => 1,
                'changed_at'         => $now->copy()->subDays(3),
                'notes'              => null,
                'created_at'         => $now->copy()->subDays(3),
                'updated_at'         => $now->copy()->subDays(3),
            ],
            [
                'order_id'           => 3,
                'status'             => 'completed',
                'changed_by_user_id' => 1,
                'changed_at'         => $now->copy()->subDays(1),
                'notes'              => 'Sudah diambil customer',
                'created_at'         => $now->copy()->subDays(1),
                'updated_at'         => $now->copy()->subDays(1),
            ],
        ]);
    }
}
