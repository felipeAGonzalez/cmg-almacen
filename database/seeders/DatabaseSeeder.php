<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RootUserSeeder::class);

        User::create([
            'name'          => 'Admin',
            'last_name_one' => 'Sistema',
            'last_name_two' => null,
            'email'         => 'admin@example.com',
            'password'      => Hash::make('Admin1234'),
            'role'          => 'admin',
        ]);

        User::create([
            'name'          => 'Usuario',
            'last_name_one' => 'Ejemplo',
            'last_name_two' => null,
            'email'         => 'user@example.com',
            'password'      => Hash::make('User1234'),
            'role'          => 'user',
        ]);
    }
}
