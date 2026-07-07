<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user for the Filament panel. Change the password after first login.
        User::updateOrCreate(
            ['email' => 'admin@larovie.ae'],
            [
                'name' => 'Larovie Admin',
                'password' => Hash::make('password'),
            ]
        );
    }
}
