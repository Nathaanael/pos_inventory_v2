<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create an admin user
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'role' => 'admin',
                'password' => bcrypt('password'),
                'must_reset_password' => false,
                'is_active' => true,
            ]
        );

        // Contoh akun kasir: hanya bisa membuka portal kasir (/kasir)
        \App\Models\User::updateOrCreate(
            ['email' => 'kasir@kasir.com'],
            [
                'name' => 'Kasir',
                'role' => \App\Models\User::ROLE_KASIR,
                'password' => bcrypt('password'),
                'must_reset_password' => false,
                'is_active' => true,
            ]
        );

        $this->call(DummyDataSeeder::class);
    }
}
