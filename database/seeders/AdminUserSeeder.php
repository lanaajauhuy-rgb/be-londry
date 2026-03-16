<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PENTING: jangan pakai Hash::make() di sini.
        // Model User sudah punya cast 'hashed' untuk kolom password.
        // Artinya Eloquent otomatis hash password saat disimpan.
        // Kalau pakai Hash::make() juga, password akan di-hash DUA KALI = tidak bisa login.
        // Cukup kirim plaintext, biarkan Model yang hash.
        User::updateOrCreate(
            ['email' => 'lananuranf@gmail.com'],
            [
                'name'          => 'Super Admin',
                'password'      => 'lana@121212', // Model otomatis hash ini
                'role'          => 'admin',
                'phone'         => '08123456789',
                'is_active'     => true,
                'last_login_at' => null,
            ]
        );
    }
}
