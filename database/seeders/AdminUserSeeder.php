<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@user.com'],
            [
                'name' => 'Admin',
                'password' => env('ADMIN_PASSWORD'),
            ],
        );
    }
}
