<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'administrator']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'administrator')
            ->update(['role' => 'admin']);
    }
};
