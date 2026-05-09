<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default admin user if it doesn't already exist
        User::updateOrCreate(
            ['email' => 'admin@behs3bahan.local'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password123'), // CHANGE THIS IN PRODUCTION
                'role_id' => 1, // Admin role
            ]
        );
    }
}
