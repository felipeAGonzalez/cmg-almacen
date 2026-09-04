<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('warehouse_rest_day')->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('operational_settings', function (Blueprint $table) {
            $table->dropColumn('warehouse_rest_day');
        });
    }
};
