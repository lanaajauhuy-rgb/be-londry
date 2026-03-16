<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('deliveries')->insert([
            // Pickup untuk ORD00001
            [
                'order_id'        => 1,
                'type'            => 'pickup',
                'address'         => '123 Main St, Jakarta',
                'scheduled_at'    => now()->subHours(6),
                'completed_at'    => now()->subHours(5),
                'courier_user_id' => null,
                'status'          => 'done',
                'notes'           => 'Dijemput tepat waktu',
                'created_at'      => now()->subHours(7),
                'updated_at'      => now()->subHours(5),
            ],
            // Delivery untuk ORD00003 (sudah completed)
            [
                'order_id'        => 3,
                'type'            => 'delivery',
                'address'         => '123 Main St, Jakarta',
                'scheduled_at'    => now()->subDays(1),
                'completed_at'    => now()->subDays(1),
                'courier_user_id' => null,
                'status'          => 'done',
                'notes'           => 'Diantar ke rumah',
                'created_at'      => now()->subDays(2),
                'updated_at'      => now()->subDays(1),
            ],
            // Pickup pending untuk ORD00002
            [
                'order_id'        => 2,
                'type'            => 'pickup',
                'address'         => '456 Elm St, Bandung',
                'scheduled_at'    => now()->addHours(3),
                'completed_at'    => null,
                'courier_user_id' => null,
                'status'          => 'pending',
                'notes'           => 'Tolong telepon dulu',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);
    }
}
