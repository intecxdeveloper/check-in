<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'nadi@expo.local'],
            [
                'name' => 'Nadi',
                'password' => Hash::make('password'),
            ]
        );
    }
}
