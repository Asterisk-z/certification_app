<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@hseboard.com'],
            [
                'name' => 'HSE Board Admin',
                'password' => 'password',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );

        $this->call(LegacyDataSeeder::class);
    }
}
