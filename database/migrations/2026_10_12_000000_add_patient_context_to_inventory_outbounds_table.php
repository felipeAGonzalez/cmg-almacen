<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_outbounds', function (Blueprint $table): void {
            $table->string('patient_name')->nullable()->after('reason');
            $table->string('room_number', 50)->nullable()->after('patient_name');
            $table->string('requesting_area', 150)->nullable()->after('room_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_outbounds', function (Blueprint $table): void {
            $table->dropColumn(['patient_name', 'room_number', 'requesting_area']);
        });
    }
};
