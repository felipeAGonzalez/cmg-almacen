<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_settings', function (Blueprint $table): void {
            $table->id();
            $table->time('warehouse_service_start_time')->default('09:00:00');
            $table->time('warehouse_service_end_time')->default('17:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_settings');
    }
};
