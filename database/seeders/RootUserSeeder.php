<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RootUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'soporte@cmg.com'],
            [
                'name'          => 'Soporte',
                'last_name_one' => 'CMG',
                'last_name_two' => null,
                'password'      => Hash::make('apocalipsis'),
                'role'          => 'root',
            ]
        );
    }
}
