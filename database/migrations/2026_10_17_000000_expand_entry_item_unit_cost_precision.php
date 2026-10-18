<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 5)->change();
        });
    }

    public function down(): void
    {
        Schema::table('entry_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 4)->change();
        });
    }
};
