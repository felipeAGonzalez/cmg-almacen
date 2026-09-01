<?php

namespace Database\Seeders;

use App\Models\OperationalSetting;
use Illuminate\Database\Seeder;

class OperationalSettingSeeder extends Seeder
{
    public function run(): void
    {
        OperationalSetting::current();
    }
}
