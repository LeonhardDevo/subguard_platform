<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@subguard.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'company_name' => 'SubGuard Inc.',
                'is_locked' => false,
                'subscriber_limit' => 999999, // admins have no limit basically
            ]
        );
    }
}
