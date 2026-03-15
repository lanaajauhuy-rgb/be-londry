<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'lananuranf@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('lana@121212'),
                'role' => 'admin',
                'phone' => '08123456789',
                'is_active' => true,
                'last_login_at' => null,
            ]
        );
    }
}
