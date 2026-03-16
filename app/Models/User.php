<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string|null $phone
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
// User extends Authenticatable, BUKAN extends Model biasa.
// Authenticatable = versi Model yang sudah dilengkapi fitur login:
//   - Auth::attempt() bisa cek password user ini
//   - Auth::user() bisa ambil object user ini
//   - Session tracking, remember me, dll
//
// ALTERNATIF: kalau tidak butuh login, cukup extends Model.
class User extends Authenticatable
{
    // 'use' di dalam class = Trait, bukan import namespace.
    // Trait = kumpulan method yang bisa "dipinjam" oleh banyak class.
    //
    // HasFactory  = menambahkan method User::factory() untuk generate data dummy saat testing.
    // Notifiable  = menambahkan kemampuan kirim notifikasi (email, SMS, push notification).
    //
    // @use HasFactory<UserFactory> = PHPDoc hint untuk IDE supaya tahu
    // factory ini menggunakan UserFactory, bukan factory lain.
    // HasApiTokens = ditambahkan oleh Sanctum.
    // Ini yang bikin User bisa punya token API.
    // Dengan trait ini, bisa panggil:
    //   $user->createToken('nama-token')  → buat token baru
    //   $user->tokens()                  → list semua token user ini
    //   $user->currentAccessToken()      → token yang dipakai di request ini
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // $fillable = kolom yang boleh diisi lewat create() atau update().
    // Sama seperti Model lain, ini proteksi Mass Assignment.
    protected $fillable = [
        'name',
        'email',
        'password',
        // role = level akses user di sistem.
        // Nilai yang valid: 'admin', 'cashier', 'operator', 'courier' (lihat migration).
        'role',
        'phone',
        'is_active',    // boolean: true = aktif bisa login, false = diblokir
        'last_login_at', // timestamp waktu login terakhir
    ];

    // $hidden = kolom yang TIDAK ikut saat model di-convert ke JSON atau array.
    // Tanpa ini, kalau return response()->json(['data' => $user]),
    // password hash dan remember_token akan terekspos ke client.
    // Selalu sembunyikan data sensitif di $hidden.
    protected $hidden = [
        'password',       // hash bcrypt, jangan pernah dikirim ke client
        'remember_token', // token "remember me", tidak relevan untuk API
    ];

    // casts() = instruksi ke Laravel untuk otomatis konversi tipe data
    // saat membaca dari database atau menyimpan ke database.
    //
    // KENAPA butuh cast?
    // Database menyimpan semua data sebagai string/integer.
    // Cast memastikan PHP menerima tipe data yang tepat.
    protected function casts(): array
    {
        return [
            // 'datetime' = kolom ini di-convert ke Carbon object (bukan string biasa).
            // Carbon adalah library tanggal/waktu di Laravel.
            // Dengan ini kamu bisa: $user->last_login_at->diffForHumans() → '2 hours ago'
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',

            // 'boolean' = nilai 0/1 di database di-convert ke false/true di PHP.
            // Tanpa ini, $user->is_active akan bernilai "1" (string), bukan true (boolean).
            'is_active' => 'boolean',

            // 'hashed' = saat $user->password = 'abc123' dieksekusi,
            // Laravel OTOMATIS hash password-nya dengan bcrypt sebelum simpan ke DB.
            // Jadi kamu tidak perlu tulis bcrypt() atau Hash::make() secara manual.
            'password' => 'hashed',
        ];
    }
}
